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
            $opponentScore = DB::table('match_results')
                ->where('match_id', $match->id)
                ->where('team_id', $opponentTeamId)
                ->sum('score');
            
            if ($opponentScore !== null && $opponentScore <= 5) {
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
            
            $opponentScore = 0;
            if (is_array($scoreData) && isset($scoreData[$loserSide])) {
                foreach ($scoreData[$loserSide] as $s) {
                    $opponentScore += (int) $s;
                }
            }

            if ($opponentScore <= 5) {
                $isGhostMatch = true;
                $winnerUserIds = DB::table('match_histories')
                    ->where('quick_match_id', $match->id)
                    ->where('team_side', $winnerSide)
                    ->pluck('user_id')->toArray();
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

            // Đếm tổng số trận "Ghost" của user này
            $totalGhostWins = $this->countGhostWins((int) $userId);
            
            if ($totalGhostWins >= 20) {
                $badgeService->awardBadge((int) $userId, $badgeCode);
            }
        }
    }

    private function countGhostWins(int $userId): int
    {
        $matchesCount = DB::selectOne("
            SELECT count(DISTINCT m.id) as cnt
            FROM matches m
            JOIN team_members tm ON tm.team_id = m.winner_id
            WHERE tm.user_id = ?
              AND m.status = 'completed'
              AND m.is_bye = 0
              AND (
                  SELECT COALESCE(SUM(score), 0)
                  FROM match_results mr
                  WHERE mr.match_id = m.id
                    AND mr.team_id = CASE WHEN m.home_team_id = m.winner_id THEN m.away_team_id ELSE m.home_team_id END
              ) <= 5
        ", [$userId])->cnt;

        $miniMatchesCount = DB::selectOne("
            SELECT count(DISTINCT mm.id) as cnt
            FROM mini_matches mm
            JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team_win_id
            WHERE mtm.user_id = ?
              AND mm.status = 'completed'
              AND mm.is_bye = 0
              AND (
                  (mm.team_win_id = mm.team1_id AND mm.team_2_score <= 5 AND mm.team_2_score IS NOT NULL)
                  OR
                  (mm.team_win_id = mm.team2_id AND mm.team_1_score <= 5 AND mm.team_1_score IS NOT NULL)
              )
        ", [$userId])->cnt;

        $miniMatchesSoloCount = DB::selectOne("
            SELECT count(DISTINCT mm.id) as cnt
            FROM mini_matches mm
            WHERE mm.participant_win_id = ?
              AND mm.status = 'completed'
              AND mm.is_bye = 0
              AND (
                  (mm.participant_win_id = mm.participant1_id AND mm.team_2_score <= 5 AND mm.team_2_score IS NOT NULL)
                  OR
                  (mm.participant_win_id = mm.participant2_id AND mm.team_1_score <= 5 AND mm.team_1_score IS NOT NULL)
              )
        ", [$userId])->cnt;

        // QuickMatches
        $quickMatches = DB::select("
            SELECT qm.score, qm.winner
            FROM quick_matches qm
            JOIN match_histories mh ON mh.quick_match_id = qm.id
            WHERE mh.user_id = ?
              AND qm.status = 'completed'
              AND qm.winner = mh.team_side
              AND qm.score IS NOT NULL
        ", [$userId]);

        $qmGhostCount = 0;
        foreach ($quickMatches as $qm) {
            $scoreData = json_decode($qm->score, true);
            if (!$scoreData) continue;
            
            $loserSide = ($qm->winner == 'team_a') ? 'team_b' : 'team_a';
            $opponentScore = 0;
            if (isset($scoreData[$loserSide])) {
                foreach ($scoreData[$loserSide] as $s) {
                    $opponentScore += (int) $s;
                }
            }
            if ($opponentScore <= 5) {
                $qmGhostCount++;
            }
        }

        return $matchesCount + $miniMatchesCount + $miniMatchesSoloCount + $qmGhostCount;
    }
}
