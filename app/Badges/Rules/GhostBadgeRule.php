<?php

namespace App\Badges\Rules;

use App\Events\MatchResultConfirmed;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class GhostBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'GHOST';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        $match = $event->match;
        $winnerUserIds = [];
        $isGhostMatch = false;

        // Lọc ngay xem TRẬN HIỆN TẠI có phải là trận "Ghost" không (đối thủ <= 5đ)
        if ($match instanceof Matches) {
            if (!$match->winner_id || $match->is_bye) return;

            $opponentTeamId = ($match->home_team_id == $match->winner_id) ? $match->away_team_id : $match->home_team_id;
            $hasScore = DB::table('match_results')
                ->where('match_id', $match->id)
                ->where('team_id', $opponentTeamId)
                ->exists();
            $opponentScore = DB::table('match_results')
                ->where('match_id', $match->id)
                ->where('team_id', $opponentTeamId)
                ->sum('score');
            
            if ($hasScore && $opponentScore <= 5) {
                $isGhostMatch = true;
                $winnerUserIds = DB::table('team_members')
                    ->where('team_id', $match->winner_id)
                    ->pluck('user_id')->toArray();
            }
        } elseif ($match instanceof MiniMatch) {
            if ($match->is_bye) return;
            
            $opponentScore = null;
            if ($match->team_win_id) {
                $opponentScore = ($match->team_win_id == $match->team1_id) ? $match->team_2_score : $match->team_1_score;
                $winnerUserIds = DB::table('mini_team_members')
                    ->where('mini_team_id', $match->team_win_id)
                    ->pluck('user_id')->toArray();
            } elseif ($match->participant_win_id) {
                $opponentScore = ($match->participant_win_id == $match->participant1_id) ? $match->team_2_score : $match->team_1_score;
                $winnerUserIds = [$match->participant_win_id];
            }

            if ($opponentScore !== null && $opponentScore <= 5) {
                $isGhostMatch = true;
            }
        } elseif ($match instanceof QuickMatch) {
            $winnerSide = $match->winner; // 'team_a' or 'team_b'
            if (!$winnerSide) return;

            $loserSide = ($winnerSide == 'team_a') ? 'team_b' : 'team_a';
            $scoreData = is_string($match->score) ? json_decode($match->score, true) : $match->score;
            
            if (is_array($scoreData) && isset($scoreData[$loserSide])) {
                $opponentScore = 0;
                foreach ($scoreData[$loserSide] as $s) {
                    $opponentScore += (int) $s;
                }

                if ($opponentScore <= 5) {
                    $isGhostMatch = true;
                    $winnerUserIds = DB::table('match_histories')
                        ->where('quick_match_id', $match->id)
                        ->where('team_side', $winnerSide)
                        ->pluck('user_id')->toArray();
                }
            }
        }

        if (!$isGhostMatch || empty($winnerUserIds)) {
            return;
        }

        $badgeService = app(BadgeService::class);
        $badgeCode = $this->badgeCode();
        $badge = \App\Models\Badge::where('code', $badgeCode)->first();
        if (!$badge) return;

        foreach ($winnerUserIds as $userId) {
            if (!$userId) continue;

            // Kiểm tra nhanh xem đã có huy hiệu này chưa
            if (UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                continue;
            }

            // Đếm chuỗi thắng "Ghost" liên tiếp của user này
            $ghostStreak = $this->calculateCurrentGhostStreak((int) $userId);
            
            if ($ghostStreak >= 20) {
                $badgeService->awardBadge((int) $userId, $badgeCode);
            }
        }
    }

    /**
     * Tính chuỗi thắng "Ghost" liên tiếp hiện tại của user bằng cách quét ngược lịch sử
     * (Dừng lại ngay khi gặp trận đầu tiên không phải Ghost Win: thua hoặc để đối thủ > 5đ)
     */
    private function calculateCurrentGhostStreak(int $userId): int
    {
        $sql = "
            SELECT 
                dt,
                match_id,
                match_type,
                is_ghost_win,
                score_raw,
                winner_side,
                user_side
            FROM (
                -- Giải đấu lớn
                SELECT 
                    m.updated_at as dt,
                    m.id as match_id,
                    'tournament' as match_type,
                    CASE 
                        WHEN m.winner_id = tm.team_id 
                         AND EXISTS (
                             SELECT 1 FROM match_results mr 
                             WHERE mr.match_id = m.id 
                               AND mr.team_id = CASE WHEN m.home_team_id = m.winner_id THEN m.away_team_id ELSE m.home_team_id END
                         )
                         AND (
                            SELECT COALESCE(SUM(score), 0)
                            FROM match_results mr
                            WHERE mr.match_id = m.id
                              AND mr.team_id = CASE WHEN m.home_team_id = m.winner_id THEN m.away_team_id ELSE m.home_team_id END
                        ) <= 5 THEN 1
                        ELSE 0
                    END as is_ghost_win,
                    NULL as score_raw,
                    NULL as winner_side,
                    NULL as user_side
                FROM matches m
                JOIN team_members tm ON (tm.team_id = m.home_team_id OR tm.team_id = m.away_team_id)
                WHERE tm.user_id = ? 
                  AND m.status = 'completed' 
                  AND m.winner_id IS NOT NULL 
                  AND COALESCE(m.is_bye, 0) = 0

                UNION ALL

                -- Giải Mini (Đấu Đội)
                SELECT 
                    mm.updated_at as dt,
                    mm.id as match_id,
                    'mini_team' as match_type,
                    CASE 
                        WHEN mm.team_win_id = mtm.mini_team_id AND (
                            (mm.team_win_id = mm.team1_id AND mm.team_2_score <= 5 AND mm.team_2_score IS NOT NULL)
                            OR
                            (mm.team_win_id = mm.team2_id AND mm.team_1_score <= 5 AND mm.team_1_score IS NOT NULL)
                        ) THEN 1
                        ELSE 0
                    END as is_ghost_win,
                    NULL as score_raw,
                    NULL as winner_side,
                    NULL as user_side
                FROM mini_matches mm
                JOIN mini_team_members mtm ON (mtm.mini_team_id = mm.team1_id OR mtm.mini_team_id = mm.team2_id)
                WHERE mtm.user_id = ? 
                  AND mm.status = 'completed' 
                  AND mm.team_win_id IS NOT NULL 
                  AND COALESCE(mm.is_bye, 0) = 0

                UNION ALL
                
                -- Giải Mini (Đấu Đơn)
                SELECT 
                    mm.updated_at as dt,
                    mm.id as match_id,
                    'mini_solo' as match_type,
                    CASE 
                        WHEN mm.participant_win_id = ? AND (
                            (mm.participant_win_id = mm.participant1_id AND mm.team_2_score <= 5 AND mm.team_2_score IS NOT NULL)
                            OR
                            (mm.participant_win_id = mm.participant2_id AND mm.team_1_score <= 5 AND mm.team_1_score IS NOT NULL)
                        ) THEN 1
                        ELSE 0
                    END as is_ghost_win,
                    NULL as score_raw,
                    NULL as winner_side,
                    NULL as user_side
                FROM mini_matches mm
                WHERE (mm.participant1_id = ? OR mm.participant2_id = ?) 
                  AND mm.status = 'completed' 
                  AND mm.participant_win_id IS NOT NULL 
                  AND COALESCE(mm.is_bye, 0) = 0

                UNION ALL

                -- Kèo / Đánh Nhanh
                SELECT 
                    qm.updated_at as dt,
                    qm.id as match_id,
                    'quick_match' as match_type,
                    0 as is_ghost_win,
                    qm.score as score_raw,
                    qm.winner as winner_side,
                    mh.team_side as user_side
                FROM quick_matches qm
                JOIN match_histories mh ON mh.quick_match_id = qm.id
                WHERE mh.user_id = ? 
                  AND qm.status = 'completed' 
                  AND qm.winner IS NOT NULL
            ) as all_matches
            ORDER BY dt DESC, match_id DESC
        ";

        $results = DB::select($sql, [$userId, $userId, $userId, $userId, $userId, $userId]);

        $streak = 0;
        foreach ($results as $row) {
            $isGhost = false;

            if ($row->match_type === 'quick_match') {
                if ($row->winner_side && $row->winner_side === $row->user_side && !empty($row->score_raw)) {
                    $scoreData = is_string($row->score_raw) ? json_decode($row->score_raw, true) : $row->score_raw;
                    $loserSide = ($row->winner_side === 'team_a') ? 'team_b' : 'team_a';
                    $opponentScore = 0;
                    if (is_array($scoreData) && isset($scoreData[$loserSide])) {
                        foreach ($scoreData[$loserSide] as $s) {
                            $opponentScore += (int) $s;
                        }
                        if ($opponentScore <= 5) {
                            $isGhost = true;
                        }
                    }
                }
            } else {
                $isGhost = ((int) $row->is_ghost_win === 1);
            }

            if ($isGhost) {
                $streak++;
                if ($streak >= 20) {
                    break;
                }
            } else {
                break; // Gặp trận không phải ghost win là đứt chuỗi
            }
        }

        return $streak;
    }
}
