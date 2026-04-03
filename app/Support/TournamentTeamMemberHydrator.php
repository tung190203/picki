<?php

namespace App\Support;

use App\Models\Participant;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Hydrate team members với tournament participant info cho Tournament API.
 *
 * Mỗi User trong team->members sẽ có relation `tournamentParticipant` đã được set.
 * Khi đó TeamMemberResource có thể đọc tournamentParticipant để:
 *   - Resolve guest_name/guest_avatar
 *   - Đóng gói nested tournament_participant cho app
 *   - Load sports từ chính User (cần ensure eager load 'sports' ở query gốc)
 */
class TournamentTeamMemberHydrator
{
    /**
     * Hydrate all teams in a collection.
     * Đảm bảo mỗi member trong team->members có:
     *   - relation `tournamentParticipant` (Participant model hoặc null)
     *   - relation `sports` đã loaded (để TeamMemberResource trả sports)
     *
     * @param Collection $teams
     * @param int $tournamentId
     * @return void
     */
    public static function hydrateCollection(Collection $teams, int $tournamentId): void
    {
        // Convert to Eloquent collection if needed for consistency
        $teams = $teams instanceof \Illuminate\Database\Eloquent\Collection
            ? $teams
            : \Illuminate\Database\Eloquent\Collection::make($teams->all());

        if ($teams->isEmpty()) {
            return;
        }

        // Collect all user IDs across all teams
        $allUserIds = $teams
            ->flatMap(fn (Team $team) => $team->members->pluck('id'))
            ->unique()
            ->values()
            ->all();

        if (empty($allUserIds)) {
            return;
        }

        // Batch load: participants + user.sports + guarantor
        $participants = Participant::where('tournament_id', $tournamentId)
            ->whereIn('user_id', $allUserIds)
            ->with(['user.sports.scores', 'user.sports.sport', 'guarantor'])
            ->get()
            ->keyBy('user_id');

        // Map user_id -> Participant để attach lên member
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                $participant = $participants->get($member->id);
                if ($participant) {
                    // Ensure member itself has sports loaded via the participant's user relation
                    // (User already loaded via team.members; ensure sports too)
                    if (!$member->relationLoaded('sports')) {
                        $member->setRelation('sports', $participant->user?->sports ?? collect());
                    }
                    $member->setRelation('tournamentParticipant', $participant);
                } else {
                    $member->setRelation('tournamentParticipant', null);
                }
            }
        }
    }

    /**
     * Hydrate a single team.
     *
     * @param Team $team
     * @param int $tournamentId
     * @return void
     */
    public static function hydrateTeam(Team $team, int $tournamentId): void
    {
        self::hydrateCollection(collect([$team]), $tournamentId);
    }
}
