<?php

namespace App\Badges\Rules;

use App\Events\MatchResultConfirmed;
use App\Models\Badge;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class WinStreakBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        5 => 'STREAK_5',
        10 => 'STREAK_10',
        15 => 'STREAK_15',
        25 => 'STREAK_25',
    ];

    public function badgeCode(): string
    {
        return 'WIN_STREAK_TIERED';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $match = $event->match;
        $winnerUserIds = [];

        // Chỉ xét những người THẮNG trong trận vừa diễn ra
        // (Người thua chắc chắn bị đứt chuỗi nên ko cần xét xem họ có đạt mốc không)
        if ($match instanceof Matches) {
            if (!$match->winner_id || $match->is_bye) return;
            $winnerUserIds = DB::table('team_members')
                ->where('team_id', $match->winner_id)
                ->pluck('user_id')->toArray();
        } elseif ($match instanceof MiniMatch) {
            if ($match->is_bye) return;
            if ($match->team_win_id) {
                $winnerUserIds = DB::table('mini_team_members')
                    ->where('mini_team_id', $match->team_win_id)
                    ->pluck('user_id')->toArray();
            } elseif ($match->participant_win_id) {
                $winnerUserIds = [$match->participant_win_id];
            }
        } elseif ($match instanceof QuickMatch) {
            $winnerSide = $match->winner; // 'team_a' or 'team_b'
            if (!$winnerSide) return;
            $winnerUserIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->where('team_side', $winnerSide)
                ->pluck('user_id')->toArray();
        }

        $winnerUserIds = array_unique(array_filter($winnerUserIds));
        if (empty($winnerUserIds)) {
            return;
        }

        $badgeService = app(BadgeService::class);

        foreach ($winnerUserIds as $userId) {
            $currentStreak = $this->calculateCurrentWinStreak((int) $userId);

            foreach ($this->tiers as $threshold => $code) {
                if ($currentStreak >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge((int) $userId, $code);
                    }
                }
            }
        }
    }

    /**
     * Tính chuỗi thắng hiện tại của user bằng cách quét ngược lịch sử
     * (Dừng lại ngay khi gặp trận thua đầu tiên -> Rất nhanh)
     */
    private function calculateCurrentWinStreak(int $userId): int
    {
        $sql = "
            SELECT is_win
            FROM (
                -- Giải đấu lớn
                SELECT 
                    m.updated_at as dt,
                    CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END as is_win
                FROM matches m
                JOIN team_members tm ON tm.team_id = m.home_team_id OR tm.team_id = m.away_team_id
                WHERE tm.user_id = ? AND m.status = 'completed' AND m.winner_id IS NOT NULL AND m.is_bye = 0

                UNION ALL

                -- Giải Mini (Đấu Đội)
                SELECT 
                    mm.updated_at as dt,
                    CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END as is_win
                FROM mini_matches mm
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id OR mtm.mini_team_id = mm.team2_id
                WHERE mtm.user_id = ? AND mm.status = 'completed' AND mm.team_win_id IS NOT NULL AND mm.is_bye = 0

                UNION ALL
                
                -- Giải Mini (Đấu Đơn)
                SELECT 
                    mm.updated_at as dt,
                    CASE WHEN mm.participant_win_id = ? THEN 1 ELSE 0 END as is_win
                FROM mini_matches mm
                WHERE (mm.participant1_id = ? OR mm.participant2_id = ?) 
                  AND mm.status = 'completed' AND mm.participant_win_id IS NOT NULL AND mm.is_bye = 0

                UNION ALL

                -- Kèo / Đánh Nhanh
                SELECT 
                    qm.updated_at as dt,
                    CASE WHEN mh.team_side = qm.winner THEN 1 ELSE 0 END as is_win
                FROM quick_matches qm
                JOIN match_histories mh ON mh.quick_match_id = qm.id
                WHERE mh.user_id = ? AND qm.status = 'completed' AND qm.winner IS NOT NULL
            ) as all_matches
            ORDER BY dt DESC
        ";

        $results = DB::select($sql, [$userId, $userId, $userId, $userId, $userId, $userId]);
        
        $streak = 0;
        foreach ($results as $row) {
            if ($row->is_win == 1) {
                $streak++;
            } else {
                break; // Gặp trận thua đầu tiên là đứt chuỗi
            }
        }
        
        return $streak;
    }
}