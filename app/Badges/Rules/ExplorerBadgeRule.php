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

class ExplorerBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        10 => 'EXPLORER_10',
        30 => 'EXPLORER_30',
        100 => 'EXPLORER_100',
        300 => 'EXPLORER_300',
    ];

    public function badgeCode(): string
    {
        return 'EXPLORER_TIERED';
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
            $venueCount = $this->countUniqueVenues((int) $userId);

            foreach ($this->tiers as $threshold => $code) {
                if ($venueCount >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($userId, $code);
                    }
                }
            }
        }
    }

    private function countUniqueVenues(int $userId): int
    {
        $query = "
            SELECT COUNT(DISTINCT location_id) as total_venues
            FROM (
                SELECT t.competition_location_id as location_id
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id IN (m.home_team_id, m.away_team_id)
                WHERE tm.user_id = ? 
                  AND m.status = 'completed' AND m.is_bye = 0
                  AND t.competition_location_id IS NOT NULL

                UNION

                SELECT mnt.competition_location_id as location_id
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id IN (mm.team1_id, mm.team2_id)
                WHERE mtm.user_id = ?
                  AND mm.status = 'completed'
                  AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
                  AND mnt.competition_location_id IS NOT NULL

                UNION
                
                SELECT mnt.competition_location_id as location_id
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_participants mp ON mp.mini_tournament_id = mnt.id
                WHERE mp.user_id = ?
                  AND (mm.participant1_id = mp.id OR mm.participant2_id = mp.id)
                  AND mm.status = 'completed'
                  AND mm.team1_id IS NULL AND mm.team2_id IS NULL
                  AND mnt.competition_location_id IS NOT NULL

                UNION

                SELECT qm.competition_location_id as location_id
                FROM match_histories mh
                JOIN quick_matches qm ON mh.quick_match_id = qm.id
                WHERE mh.user_id = ?
                  AND qm.status = 'completed'
                  AND qm.competition_location_id IS NOT NULL
            ) AS unique_venues
        ";

        $result = DB::selectOne($query, [$userId, $userId, $userId, $userId]);
        return $result ? (int) $result->total_venues : 0;
    }
}
