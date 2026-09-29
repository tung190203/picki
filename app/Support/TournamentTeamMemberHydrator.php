<?php

namespace App\Support;

use App\Models\Participant;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Hydrate team members với tournament participant info cho Tournament API.
 *
 * Hỗ trợ cả real user (qua team_members.user_id) lẫn guest participant
 * (qua team_members.participant_id, không có User).
 *
 * Sau khi hydrate:
 *   - Mỗi User trong $team->members có relation `tournamentParticipant`
 *   - Mỗi TeamMember trong $team->guestMembers có participant đã load
 *     sport/score/guarantor (để TeamMemberResource đọc).
 */
class TournamentTeamMemberHydrator
{
    public static function hydrateCollection(Collection $teams, int $tournamentId): void
    {
        $teams = $teams instanceof \Illuminate\Database\Eloquent\Collection
            ? $teams
            : \Illuminate\Database\Eloquent\Collection::make($teams->all());

        if ($teams->isEmpty()) {
            return;
        }

        $userIds = $teams
            ->flatMap(fn (Team $team) => $team->members->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $guestParticipantIds = $teams
            ->flatMap(fn (Team $team) => $team->guestMembers->pluck('participant_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds) && empty($guestParticipantIds)) {
            return;
        }

        $participantsByUser = collect();
        if (!empty($userIds)) {
            $participantsByUser = Participant::where('tournament_id', $tournamentId)
                ->whereIn('user_id', $userIds)
                ->with(['user.sports.scores', 'user.sports.sport', 'guarantor'])
                ->get()
                ->keyBy('user_id');
        }

        // Cũng load participants cho guest member rows (qua participant_id) để đảm bảo
        // relation `user.sports/guarantor` đã eager loaded trước khi TeamResource build response.
        if (!empty($guestParticipantIds)) {
            Participant::where('tournament_id', $tournamentId)
                ->whereIn('id', $guestParticipantIds)
                ->with(['user.sports.scores', 'user.sports.sport', 'guarantor'])
                ->get()
                ->each(function ($p) use ($participantsByUser) {
                    if ($p->user_id && !$participantsByUser->has($p->user_id)) {
                        $participantsByUser->put($p->user_id, $p);
                    }
                });
        }

        foreach ($teams as $team) {
            // Set tournamentParticipant cho real user members
            foreach ($team->members as $member) {
                $participant = $participantsByUser->get($member->id);
                if ($participant) {
                    if (!$member->relationLoaded('sports')) {
                        $member->setRelation('sports', $participant->user?->sports ?? collect());
                    }
                    $member->setRelation('tournamentParticipant', $participant);
                } else {
                    $member->setRelation('tournamentParticipant', null);
                }
            }

            // Set tournamentParticipant cho guest members (chính là participant model)
            foreach ($team->guestMembers as $tm) {
                if ($tm->participant) {
                    $tm->participant->setRelation('tournamentParticipant', $tm->participant);
                    $tm->participant->setRelation('sports', collect());
                }
            }
        }
    }

    public static function hydrateTeam(Team $team, int $tournamentId): void
    {
        self::hydrateCollection(collect([$team]), $tournamentId);
    }
}