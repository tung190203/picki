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

class TouringPlayerBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        3 => 'TOURING_PLAYER_3',
        10 => 'TOURING_PLAYER_10',
        30 => 'TOURING_PLAYER_30',
        100 => 'TOURING_PLAYER_100',
    ];

    public function badgeCode(): string
    {
        return 'TOURING_PLAYER_TIERED';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $match = $event->match;
        $userIds = [];

        // Identify users who participated in this match
        if ($match instanceof Matches) {
            $userIds = DB::table('team_members')
                ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
                ->pluck('user_id')->toArray();
        } elseif ($match instanceof MiniMatch) {
            $userIds = DB::table('mini_team_members')
                ->whereIn('mini_team_id', [$match->team1_id, $match->team2_id])
                ->pluck('user_id')->toArray();
            
            if (empty($userIds) && $match->participant1_id && $match->participant2_id) {
                $userIds = DB::table('mini_participants')
                    ->whereIn('id', [$match->participant1_id, $match->participant2_id])
                    ->pluck('user_id')->toArray();
            }
        } elseif ($match instanceof QuickMatch) {
            // Quick Matches usually do not belong to a Club in Vpick, 
            // but we still process them just in case schema changes later.
            $userIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->pluck('user_id')->toArray();
        }

        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return;
        }

        $badgeService = app(BadgeService::class);

        foreach ($userIds as $userId) {
            $clubCount = $this->countUniqueClubs((int) $userId);

            foreach ($this->tiers as $threshold => $code) {
                if ($clubCount >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($userId, $code);
                    }
                }
            }
        }
    }

    private function countUniqueClubs(int $userId): int
    {
        $query = "
            SELECT COUNT(DISTINCT club_id) as total_clubs
            FROM (
                SELECT t.club_id as club_id
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id IN (m.home_team_id, m.away_team_id)
                WHERE tm.user_id = ? 
                  AND m.status = 'completed' AND m.is_bye = 0
                  AND t.club_id IS NOT NULL

                UNION

                SELECT mnt.club_id as club_id
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id IN (mm.team1_id, mm.team2_id)
                WHERE mtm.user_id = ?
                  AND mm.status = 'completed'
                  AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
                  AND mnt.club_id IS NOT NULL

                UNION
                
                SELECT mnt.club_id as club_id
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_participants mp ON mp.mini_tournament_id = mnt.id
                WHERE mp.user_id = ?
                  AND (mm.participant1_id = mp.id OR mm.participant2_id = mp.id)
                  AND mm.status = 'completed'
                  AND mm.team1_id IS NULL AND mm.team2_id IS NULL
                  AND mnt.club_id IS NOT NULL
            ) AS unique_clubs
        ";

        $result = DB::selectOne($query, [$userId, $userId, $userId]);
        return $result ? (int) $result->total_clubs : 0;
    }
}
