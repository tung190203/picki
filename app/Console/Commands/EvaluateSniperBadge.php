<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Badge;
use App\Services\BadgeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EvaluateSniperBadge extends Command
{
    protected $signature = 'badges:evaluate-sniper {--month= : Tháng cần xét (YYYY-MM), mặc định là tháng trước} {--min-matches=10} {--win-rate=90}';
    
    protected $description = 'Đánh giá và phát huy hiệu Sniper (90% thắng trong tháng) cho người chơi';

    public function handle(BadgeService $badgeService): int
    {
        $monthStr = $this->option('month');
        if ($monthStr) {
            $startDate = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        } else {
            // Mặc định là tháng trước
            $startDate = Carbon::now()->subMonth()->startOfMonth();
        }
        
        $endDate = $startDate->copy()->endOfMonth();
        $minMatches = (int) $this->option('min-matches');
        $winRateThreshold = (float) $this->option('win-rate');

        $this->info("Đang tính toán huy hiệu Sniper cho tháng: " . $startDate->format('m/Y'));
        $this->info("Điều kiện: Tối thiểu {$minMatches} trận, Win rate >= {$winRateThreshold}%");

        // Đảm bảo huy hiệu tồn tại
        $badge = Badge::where('code', 'SNIPER')->first();
        if (!$badge) {
            $this->error("Không tìm thấy huy hiệu có mã SNIPER trong hệ thống!");
            return 1;
        }

        // Lấy danh sách ID của tất cả user đã tham gia ít nhất 1 trận trong tháng này
        // (Để tối ưu, ta gom từ 3 bảng: matches, mini_matches, quick_matches)
        $startStr = $startDate->toDateTimeString();
        $endStr = $endDate->toDateTimeString();

        $tUsers = DB::table('team_members')
            ->join('matches', function($join) {
                $join->on('team_members.team_id', '=', 'matches.home_team_id')
                     ->orOn('team_members.team_id', '=', 'matches.away_team_id');
            })
            ->where('matches.status', 'completed')
            ->whereBetween('matches.updated_at', [$startStr, $endStr])
            ->pluck('team_members.user_id')->toArray();

        $mUsers = DB::table('mini_team_members')
            ->join('mini_matches', function($join) {
                $join->on('mini_team_members.mini_team_id', '=', 'mini_matches.team1_id')
                     ->orOn('mini_team_members.mini_team_id', '=', 'mini_matches.team2_id');
            })
            ->where('mini_matches.status', 'completed')
            ->whereBetween('mini_matches.updated_at', [$startStr, $endStr])
            ->pluck('mini_team_members.user_id')->toArray();

        $qUsers = DB::table('match_histories')
            ->join('quick_matches', 'match_histories.quick_match_id', '=', 'quick_matches.id')
            ->where('quick_matches.status', 'completed')
            ->whereBetween('quick_matches.updated_at', [$startStr, $endStr])
            ->pluck('match_histories.user_id')->toArray();

        $activeUserIds = array_unique(array_merge($tUsers, $mUsers, $qUsers));
        $activeUserIds = array_filter($activeUserIds);

        if (empty($activeUserIds)) {
            $this->info("Không có người chơi nào thi đấu trong tháng này.");
            return 0;
        }

        $this->info("Tìm thấy " . count($activeUserIds) . " người chơi có thi đấu. Đang tính tỷ lệ thắng...");

        $userIdsCsv = implode(',', $activeUserIds);

        $sql = "
            SELECT user_id, SUM(played) as total_played, SUM(won) as total_won
            FROM (
                SELECT tm.user_id, 1 as played, CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END as won
                FROM matches m
                JOIN team_members tm ON (tm.team_id = m.home_team_id OR tm.team_id = m.away_team_id)
                WHERE m.status = 'completed' AND m.winner_id IS NOT NULL AND m.is_bye = 0
                AND m.updated_at BETWEEN ? AND ?
                AND tm.user_id IN ($userIdsCsv)

                UNION ALL

                SELECT mtm.user_id, 1, CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END
                FROM mini_matches mm
                JOIN mini_team_members mtm ON (mtm.mini_team_id = mm.team1_id OR mtm.mini_team_id = mm.team2_id)
                WHERE mm.status = 'completed' AND mm.team_win_id IS NOT NULL
                AND mm.updated_at BETWEEN ? AND ?
                AND mtm.user_id IN ($userIdsCsv)
                
                UNION ALL
                
                SELECT mh.user_id, 1, CASE WHEN mh.team_side = qm.winner THEN 1 ELSE 0 END
                FROM quick_matches qm
                JOIN match_histories mh ON mh.quick_match_id = qm.id
                WHERE qm.status = 'completed' AND qm.winner IS NOT NULL
                AND qm.updated_at BETWEEN ? AND ?
                AND mh.user_id IN ($userIdsCsv)
            ) as all_matches
            GROUP BY user_id
        ";

        $results = DB::select($sql, [$startStr, $endStr, $startStr, $endStr, $startStr, $endStr]);

        $awardedCount = 0;
        foreach ($results as $row) {
            $total = (int) $row->total_played;
            $won = (int) $row->total_won;

            if ($total >= $minMatches) {
                $winRate = ($won / $total) * 100;
                
                if ($winRate >= $winRateThreshold) {
                    $res = $badgeService->awardBadge((int) $row->user_id, 'SNIPER');
                    if ($res) {
                        $this->info("User ID {$row->user_id} đạt Sniper (Win Rate: " . round($winRate, 2) . "% | {$won}/{$total})");
                        $awardedCount++;
                    }
                }
            }
        }

        $this->info("Đã phát huy hiệu SNIPER cho {$awardedCount} người chơi.");
        return 0;
    }
}
