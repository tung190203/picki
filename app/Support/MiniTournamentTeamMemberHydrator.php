<?php

namespace App\Support;

use App\Models\MiniParticipant;
use App\Models\MiniTeam;
use Illuminate\Support\Collection;

/**
 * Hydrate team members với mini tournament participant info cho kèo đấu.
 *
 * Mỗi User trong team->members sẽ có relation `miniTournamentParticipant` đã được set.
 * Khi đó MiniTeamResource có thể đọc miniTournamentParticipant để:
 *   - Resolve guest_name/guest_avatar
 *   - Đóng gói nested mini_tournament_participant cho app
 */
class MiniTournamentTeamMemberHydrator
{
    /**
     * Hydrate all mini teams in a collection.
     * Đảm bảo mỗi member trong team->members có:
     *   - relation `miniTournamentParticipant` (MiniParticipant model hoặc null)
     *
     * @param Collection $teams
     * @param int $miniTournamentId
     * @return void
     */
    public static function hydrateCollection(Collection $teams, int $miniTournamentId): void
    {
        $teams = $teams instanceof \Illuminate\Database\Eloquent\Collection
            ? $teams
            : \Illuminate\Database\Eloquent\Collection::make($teams->all());

        if ($teams->isEmpty()) {
            return;
        }

        $allUserIds = $teams
            ->flatMap(fn (MiniTeam $team) => $team->members->pluck('user_id'))
            ->unique()
            ->values()
            ->all();

        if (empty($allUserIds)) {
            return;
        }

        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('user_id', $allUserIds)
            ->get()
            ->keyBy('user_id');

        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                $participant = $participants->get($member->user_id);
                if ($participant) {
                    $member->setRelation('miniTournamentParticipant', $participant);
                } else {
                    $member->setRelation('miniTournamentParticipant', null);
                }
            }
        }
    }

    /**
     * Hydrate a single mini team.
     *
     * @param MiniTeam $team
     * @param int $miniTournamentId
     * @return void
     */
    public static function hydrateTeam(MiniTeam $team, int $miniTournamentId): void
    {
        self::hydrateCollection(collect([$team]), $miniTournamentId);
    }
}
