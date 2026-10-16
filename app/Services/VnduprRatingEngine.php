<?php

namespace App\Services;

use App\Models\User;
use App\Models\VnduprHistory;
use Illuminate\Support\Facades\DB;

/**
 * VnduprRatingEngine — refactor logic tính VNDUPR rating khỏi MatchesController
 * để dùng chung cho cả side-out (1 trận) và rally (1 stage).
 *
 * Nguyên tắc Phase 4:
 * - KHÔNG đổi thuật toán rating cốt lõi (ELO + K-factor + W). Logic giống hệt
 *   code cũ trong MatchesController::processMatchCompletionBig.
 * - Không cộng dồn rating mới lên rating cũ một cách mù quáng khi sửa:
 *   `reverse()` restore về score_before trước, `computeAndApply()` tính lại.
 * - Idempotent: nếu đã có history cho (user, match, match_stage) thì skip.
 */
class VnduprRatingEngine
{
    /**
     * Tính rating cho 1 trận (side-out, match_stage_id=null) hoặc 1 stage (rally).
     *
     * Đầu vào:
     *  - home_member_user_ids, away_member_user_ids: danh sách user_ids tham gia
     *  - home_score, away_score: stage_score hoặc tổng set
     *  - winner_team_id: null nếu hòa
     *  - match_id, match_stage_id
     *
     * Hành vi:
     *  - Skip user nếu đã có VnduprHistory row cho (user, match, match_stage).
     *  - Insert VnduprHistory, update user_sport_scores.
     *
     * @param array $inputs
     * @return array { 'history_records' => int, 'score_upserts' => int, 'skipped' => int }
     */
    public function computeAndApply(array $inputs): array
    {
        $homeUserIds = array_values(array_unique(array_map('intval', $inputs['home_member_user_ids'] ?? [])));
        $awayUserIds = array_values(array_unique(array_map('intval', $inputs['away_member_user_ids'] ?? [])));
        $homeScore = (int) ($inputs['home_score'] ?? 0);
        $awayScore = (int) ($inputs['away_score'] ?? 0);
        $winnerTeamId = $inputs['winner_team_id'] ?? null;
        $matchId = (int) ($inputs['match_id'] ?? 0);
        $matchStageId = isset($inputs['match_stage_id']) ? (int) $inputs['match_stage_id'] : null;
        $sportId = (int) ($inputs['sport_id'] ?? 0);

        if ($matchId <= 0 || $sportId <= 0) {
            return ['history_records' => 0, 'score_upserts' => 0, 'skipped' => 0];
        }

        $allUserIds = array_values(array_unique(array_merge($homeUserIds, $awayUserIds)));
        if (empty($allUserIds)) {
            return ['history_records' => 0, 'score_upserts' => 0, 'skipped' => 0];
        }

        // ===== S (Actual Score) =====
        $totalScore = $homeScore + $awayScore;
        $S_match_home = $winnerTeamId !== null && $winnerTeamId === ($inputs['home_team_id'] ?? null) ? 1.0 : 0.0;
        $S_match_away = $winnerTeamId !== null && $winnerTeamId === ($inputs['away_team_id'] ?? null) ? 1.0 : 0.0;
        $S_points_home = $totalScore > 0 ? $homeScore / $totalScore : 0;
        $S_points_away = $totalScore > 0 ? $awayScore / $totalScore : 0;
        $S_home = 0.5 * $S_match_home + 0.5 * $S_points_home;
        $S_away = 0.5 * $S_match_away + 0.5 * $S_points_away;

        // ===== R (Average Rating) =====
        $userSportRecords = DB::table('user_sport')
            ->whereIn('user_id', $allUserIds)
            ->where('sport_id', $sportId)
            ->get()
            ->keyBy('user_id');

        $userSportIds = $userSportRecords->pluck('id')->values();
        $scoreMap = $userSportIds->isEmpty()
            ? collect()
            : DB::table('user_sport_scores')
                ->whereIn('user_sport_id', $userSportIds)
                ->where('score_type', 'vndupr_score')
                ->get()
                ->keyBy('user_sport_id');

        $avgFor = function (array $userIds) use ($userSportRecords, $scoreMap): float {
            if (empty($userIds)) {
                return 0.0;
            }
            $total = 0.0;
            $count = 0;
            foreach ($userIds as $uid) {
                $us = $userSportRecords->get($uid);
                if ($us) {
                    $sc = $scoreMap->get($us->id);
                    if ($sc) {
                        $total += (float) $sc->score_value;
                        $count++;
                    }
                }
            }
            return $count > 0 ? $total / $count : 0.0;
        };
        $homeRating = $avgFor($homeUserIds);
        $awayRating = $avgFor($awayUserIds);

        $E_home = 1 / (1 + pow(10, $awayRating - $homeRating));
        $E_away = 1 / (1 + pow(10, $homeRating - $awayRating));

        $W = 0.6;

        // ===== History cho K-factor =====
        $historyMap = VnduprHistory::whereIn('user_id', $allUserIds)
            ->orderByDesc('id')
            ->take(15 * count($allUserIds))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($col) => $col->sortBy('id')->values());

        // Lấy users kèm attributes cần thiết cho K-factor
        $userMap = User::whereIn('id', $allUserIds)->get()->keyBy('id');

        // Skip user đã có history cho (match, match_stage) — idempotent
        $existing = VnduprHistory::where('match_id', $matchId)
            ->where('match_stage_id', $matchStageId)
            ->whereIn('user_id', $allUserIds)
            ->pluck('user_id')
            ->all();
        $existingSet = array_flip(array_map('intval', $existing));

        $vnduprHistoryRecords = [];
        $scoreUpserts = [];
        $skipped = 0;
        $now = now();

        $process = function (int $userId, float $S, float $E) use (
            &$vnduprHistoryRecords,
            &$scoreUpserts,
            &$skipped,
            $userSportRecords,
            $scoreMap,
            $historyMap,
            $userMap,
            $matchId,
            $matchStageId,
            $W,
            $now,
            $existingSet
        ) {
            if (isset($existingSet[$userId])) {
                $skipped++;
                return;
            }

            $user = $userMap->get($userId);
            if (!$user || $user->is_guest) {
                // Guest bỏ qua (giữ rule cũ)
                return;
            }

            $userSport = $userSportRecords->get($userId);
            $scoreRecord = $userSport ? $scoreMap->get($userSport->id) : null;
            $R_old = $scoreRecord ? (float) $scoreRecord->score_value : 0.0;

            $history = $historyMap->get($userId, collect());

            // K-factor theo rule cũ
            $K = 0.3;
            if ($user->is_anchor || $user->hasAnyBadge(['PICKI', 'CHAMPION', 'ANCHOR', 'VERIFIED'])) {
                $K = 0.1;
            } else {
                $anchored = $user->total_matches_has_anchor ?? 0;
                if ($anchored <= 10) {
                    $K = 1;
                } elseif ($anchored <= 50) {
                    $K = 0.6;
                }
            }
            if ($history->count() >= 2) {
                $first = $history->first()->score_before;
                $last = $history->last()->score_after;
                if (($first - $last) > 0.5) {
                    $K = 1;
                }
            }

            $R_new = $R_old + ($W * $K * ($S - $E));

            $vnduprHistoryRecords[] = [
                'user_id' => $userId,
                'match_id' => $matchId,
                'match_stage_id' => $matchStageId,
                'mini_match_id' => null,
                'score_before' => $R_old,
                'score_after' => $R_new,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($userSport) {
                $scoreUpserts[] = [
                    'user_sport_id' => $userSport->id,
                    'score_type' => 'vndupr_score',
                    'score_value' => $R_new,
                    'updated_at' => $now,
                ];
            }
        };

        foreach ($homeUserIds as $uid) {
            $process($uid, $S_home, $E_home);
        }
        foreach ($awayUserIds as $uid) {
            $process($uid, $S_away, $E_away);
        }

        if (!empty($vnduprHistoryRecords)) {
            VnduprHistory::insert($vnduprHistoryRecords);
        }
        foreach ($scoreUpserts as $u) {
            DB::table('user_sport_scores')->updateOrInsert(
                ['user_sport_id' => $u['user_sport_id'], 'score_type' => $u['score_type']],
                ['score_value' => $u['score_value'], 'updated_at' => $u['updated_at']],
            );
        }

        return [
            'history_records' => count($vnduprHistoryRecords),
            'score_upserts' => count($scoreUpserts),
            'skipped' => $skipped,
        ];
    }

    /**
     * Reverse (undo) rating đã ghi cho 1 trận (side-out) hoặc 1 stage (rally).
     *
     * - Với mỗi VnduprHistory row, restore user_sport_scores.score_value = score_before
     *   (theo user_sport_id, score_type='vndupr_score').
     * - Xóa các history rows đó để recompute sạch.
     *
     * Sau reverse, gọi lại computeAndApply() sẽ tính lại từ đúng điểm khởi đầu.
     *
     * @param int $matchId
     * @param int|null $matchStageId null = toàn trận (side-out), số = 1 stage
     * @return int số history rows đã reverse
     */
    public function reverse(int $matchId, ?int $matchStageId): int
    {
        $query = VnduprHistory::where('match_id', $matchId);
        if ($matchStageId === null) {
            $query->whereNull('match_stage_id');
        } else {
            $query->where('match_stage_id', $matchStageId);
        }
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        // Group by user_sport để restore score_value
        $userIds = $rows->pluck('user_id')->unique()->values();
        $userSportRecords = DB::table('user_sport')
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $now = now();
        foreach ($rows as $row) {
            $us = $userSportRecords->get($row->user_id);
            if ($us) {
                DB::table('user_sport_scores')
                    ->where('user_sport_id', $us->id)
                    ->where('score_type', 'vndupr_score')
                    ->update(['score_value' => $row->score_before, 'updated_at' => $now]);
            }
        }

        VnduprHistory::whereIn('id', $rows->pluck('id'))->delete();

        return $rows->count();
    }
}
