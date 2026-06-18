<?php

namespace App\Console\Commands;

use App\Models\MiniMatch;
use App\Models\MiniMatchResult;
use App\Models\MiniTeamMember;
use App\Models\VnduprHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMiniMatchVnduprHistory extends Command
{
    protected $signature = 'vndupr:backfill-mini-match
        {--dry-run : Chỉ hiển thị thay đổi mà không lưu vào database}
        {--mini-match-id= : Chỉ xử lý một mini_match cụ thể}';

    protected $description = 'Backfill score_before và score_after trong vndupr_history cho các mini_match đã lưu sai (do bug dùng nhầm member_id thay vì user_id)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $targetMatchId = $this->option('mini-match-id');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Chế độ chỉ xem - không lưu thay đổi vào database');
            $this->newLine();
        }

        $this->info('=== Bắt đầu backfill vndupr_history cho Mini-Match ===');
        $this->newLine();

        // Lấy tất cả mini_match_id có trong vndupr_history (chỉ mini_match, không phải quick_match hay match thường)
        $query = VnduprHistory::query()
            ->whereNotNull('mini_match_id')
            ->whereNull('match_id')
            ->whereNull('quick_match_id')
            ->select('mini_match_id')
            ->distinct()
            ->orderByRaw('
                (SELECT created_at FROM vndupr_history v2
                 WHERE v2.mini_match_id = vndupr_history.mini_match_id
                 ORDER BY id LIMIT 1)
            ');

        if ($targetMatchId) {
            $matchIds = [(int) $targetMatchId];
            $this->info("Chỉ xử lý mini_match_id: {$targetMatchId}");
        } else {
            $matchIds = $query->pluck('mini_match_id')->toArray();
        }

        if (empty($matchIds)) {
            $this->warn('Không có mini_match nào trong vndupr_history để backfill.');
            return 0;
        }

        $this->info('Tổng cộng: ' . count($matchIds) . ' mini_match cần xử lý');
        $this->newLine();

        $totalHistoryFixed = 0;

        foreach ($matchIds as $matchId) {
            $count = $this->processMiniMatch((int) $matchId, $isDryRun);
            $totalHistoryFixed += $count;
        }

        $this->newLine();
        $this->info("=== Hoàn tất ===");
        $this->info("Tổng số bản ghi vndupr_history đã sửa: {$totalHistoryFixed}");

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Không có thay đổi nào được lưu. Chạy lại mà không có --dry-run để áp dụng.');
        }

        return 0;
    }

    protected function processMiniMatch(int $matchId, bool $isDryRun): int
    {
        $match = MiniMatch::with([
            'miniTournament',
            'team1.members',
            'team2.members',
            'results',
        ])->find($matchId);

        if (!$match) {
            $this->line("  [SKIP] MiniMatch #{$matchId} - không tìm thấy");
            return 0;
        }

        $tournament = $match->miniTournament;
        if (!$tournament) {
            $this->line("  [SKIP] MiniMatch #{$matchId} - không có tournament");
            return 0;
        }

        $sportId = $tournament->sport_id;
        $createdAt = $match->created_at;

        // Lấy tất cả user trong trận đấu
        $allMemberUserIds = $match->team1->members->pluck('user_id')
            ->merge($match->team2->members->pluck('user_id'))
            ->unique()
            ->values()
            ->toArray();

        // Lấy score_before cho mỗi user: score_after của bản ghi gần nhất TRƯỚC trận này
        $scoreBeforeMap = [];
        foreach ($allMemberUserIds as $userId) {
            $lastHistory = VnduprHistory::where('user_id', $userId)
                ->where('mini_match_id', '<>', $matchId)
                ->where('created_at', '<', $createdAt)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastHistory) {
                $scoreBeforeMap[$userId] = (float) $lastHistory->score_after;
            } else {
                // Fallback: thử lấy từ user_sport_scores
                $scoreFromSport = DB::table('user_sport_scores')
                    ->whereIn('user_sport_id', function ($q) use ($userId, $sportId) {
                        $q->select('id')
                            ->from('user_sport')
                            ->where('user_id', $userId)
                            ->where('sport_id', $sportId);
                    })
                    ->where('score_type', 'vndupr_score')
                    ->value('score_value');

                $scoreBeforeMap[$userId] = $scoreFromSport !== null ? (float) $scoreFromSport : 0.0;
            }
        }

        // Tính score_after theo đúng công thức (tái tạo lại logic từ processMatchCompletion)
        $scoreAfterMap = $this->calculateScoreAfter($match, $sportId, $allMemberUserIds, $scoreBeforeMap);

        // Lấy các bản ghi vndupr_history hiện tại của trận này
        // CHỈ lấy những bản ghi có score_before = 0 (bị lưu sai do bug)
        $currentHistory = VnduprHistory::where('mini_match_id', $matchId)
            ->whereNull('match_id')
            ->whereNull('quick_match_id')
            ->where('score_before', 0)
            ->get();

        if ($currentHistory->isEmpty()) {
            $this->line("  [SKIP] MiniMatch #{$matchId} - không có vndupr_history");
            return 0;
        }

        $this->line("  [PROCESS] MiniMatch #{$matchId} | sport_id:{$sportId} | users: " . implode(',', $allMemberUserIds));

        $fixedCount = 0;

        foreach ($currentHistory as $history) {
            $userId = $history->user_id;
            $expectedBefore = $scoreBeforeMap[$userId] ?? null;
            $expectedAfter = $scoreAfterMap[$userId] ?? null;

            if ($expectedBefore === null || $expectedAfter === null) {
                continue;
            }

            $currentBefore = (float) $history->score_before;
            $currentAfter = (float) $history->score_after;

            // Chỉ sửa nếu có sự khác biệt
            if (abs($currentBefore - $expectedBefore) > 0.0001 || abs($currentAfter - $expectedAfter) > 0.0001) {
                $change = sprintf(
                    '%.3f→%.3f / %.3f→%.3f',
                    $currentBefore, $expectedBefore,
                    $currentAfter, $expectedAfter
                );

                if ($isDryRun) {
                    $this->line("    [DRY-RUN] user_id:{$userId} | {$change}");
                } else {
                    $history->score_before = $expectedBefore;
                    $history->score_after = $expectedAfter;
                    $history->save();
                    $this->line("    [UPDATED] user_id:{$userId} | {$change}");
                }
                $fixedCount++;
            }
        }

        if ($fixedCount === 0) {
            $this->line("    [OK] Không có thay đổi");
        }

        return $fixedCount;
    }

    protected function calculateScoreAfter(MiniMatch $match, int $sportId, array $allMemberUserIds, array $scoreBeforeMap): array
    {
        $scoreAfterMap = [];

        // Lấy K-factor và total_matches hiện tại của từng user
        $userDataMap = DB::table('users')
            ->whereIn('id', $allMemberUserIds)
            ->select('id', 'is_anchor', 'total_matches_has_anchor')
            ->get()
            ->keyBy('id');

        // Batch load user_sport và score
        $userSportRecords = DB::table('user_sport')
            ->whereIn('user_id', $allMemberUserIds)
            ->where('sport_id', $sportId)
            ->get()
            ->keyBy('user_id');

        $userSportIds = $userSportRecords->pluck('id')->values();

        $scoreMap = DB::table('user_sport_scores')
            ->whereIn('user_sport_id', $userSportIds)
            ->where('score_type', 'vndupr_score')
            ->get()
            ->keyBy('user_sport_id');

        // Tính E (rating trung bình của mỗi team)
        $calcAvgRating = function ($teamId) use ($match, $userSportRecords, $scoreMap) {
            $members = $teamId === $match->team1_id
                ? $match->team1->members
                : $match->team2->members;

            $total = 0;
            $count = 0;
            foreach ($members as $member) {
                $userSport = $userSportRecords->get($member->user_id);
                if ($userSport) {
                    $score = $scoreMap->get($userSport->id);
                    if ($score) {
                        $total += (float) $score->score_value;
                        $count++;
                    }
                }
            }
            return $count > 0 ? $total / $count : 0;
        };

        $R_t1 = $calcAvgRating($match->team1_id);
        $R_t2 = $calcAvgRating($match->team2_id);

        $E_t1 = 1 / (1 + pow(10, ($R_t2 - $R_t1)));
        $E_t2 = 1 / (1 + pow(10, ($R_t1 - $R_t2)));

        // Tính S (Actual Score)
        $scores = $match->results->groupBy('team_id')->map->sum('score');
        $t1Score = $scores->get($match->team1_id, 0);
        $t2Score = $scores->get($match->team2_id, 0);
        $totalScore = $t1Score + $t2Score;

        $winnerTeamId = $match->team_win_id;
        $S_match_t1 = $winnerTeamId === $match->team1_id ? 1.0 : 0.0;
        $S_match_t2 = $winnerTeamId === $match->team2_id ? 1.0 : 0.0;
        $S_points_t1 = $totalScore > 0 ? $t1Score / $totalScore : 0;
        $S_points_t2 = $totalScore > 0 ? $t2Score / $totalScore : 0;
        $S_t1 = (0.5 * $S_match_t1) + (0.5 * $S_points_t1);
        $S_t2 = (0.5 * $S_match_t2) + (0.5 * $S_points_t2);

        // Tính score_after cho mỗi user
        $team1UserIds = $match->team1->members->pluck('user_id')->toArray();
        $team2UserIds = $match->team2->members->pluck('user_id')->toArray();

        $userScoreMap = [];
        foreach ($team1UserIds as $userId) {
            $userScoreMap[$userId] = ['S' => $S_t1, 'E' => $E_t1];
        }
        foreach ($team2UserIds as $userId) {
            $userScoreMap[$userId] = ['S' => $S_t2, 'E' => $E_t2];
        }

        foreach ($allMemberUserIds as $userId) {
            $userData = $userDataMap->get($userId);
            $R_old = $scoreBeforeMap[$userId] ?? 0;
            $scoreData = $userScoreMap[$userId] ?? ['S' => 0, 'E' => 0];

            $K = 0.3;
            if ($userData) {
                if ($userData->is_anchor) {
                    $K = 0.1;
                } elseif (($userData->total_matches_has_anchor ?? 0) <= 10) {
                    $K = 1;
                } elseif (($userData->total_matches_has_anchor ?? 0) <= 50) {
                    $K = 0.6;
                }
            }

            $R_new = $R_old + (0.2 * $K * ($scoreData['S'] - $scoreData['E']));
            $scoreAfterMap[$userId] = $R_new;
        }

        return $scoreAfterMap;
    }
}
