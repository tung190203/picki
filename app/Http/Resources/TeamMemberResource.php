<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * $this resource = User (member) với relation tournamentParticipant đã được hydrate
     * bởi TournamentTeamMemberHydrator.
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\Participant|null $participant */
        $participant = $this->relationLoaded('tournamentParticipant')
            ? $this->tournamentParticipant
            : null;

        $isGuest = (bool) ($participant?->is_guest);

        // Resolve display name & avatar từ guest-aware participant
        if ($isGuest) {
            $fullName = $participant->guest_name ?? $this->full_name;
            $avatarUrl = $participant->guest_avatar ?? $this->avatar_url;
        } else {
            $fullName = $this->full_name;
            $avatarUrl = $this->avatar_url;
        }

        // Build sports: guest dùng estimated_level, user thường dùng vndupr_score
        $sportsLoaded = $this->relationLoaded('sports')
            ? $this->sports
            : collect();

        $sportsArray = [];
        foreach ($sportsLoaded as $sport) {
            $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
            $types = ['personal_score', 'dupr_score', 'vndupr_score'];
            $formattedScores = [];
            foreach ($types as $type) {
                $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
                $scoreValue = $latestScore ? $latestScore->score_value : 0;
                $formattedScores[$type] = number_format($scoreValue, 3);
            }

            $totalMatches = DB::table('vndupr_history')
                    ->join('matches', 'vndupr_history.match_id', '=', 'matches.id')
                    ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
                    ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
                    ->where('vndupr_history.user_id', $this->id)
                    ->where('tournaments.sport_id', $sport->sport_id)
                    ->count()
                + DB::table('vndupr_history')
                    ->join('mini_matches', 'vndupr_history.mini_match_id', '=', 'mini_matches.id')
                    ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
                    ->where('vndupr_history.user_id', $this->id)
                    ->where('mini_tournaments.sport_id', $sport->sport_id)
                    ->count();

            $matchIds = DB::table('vndupr_history')
                ->where('user_id', $this->id)
                ->whereNotNull('match_id')
                ->pluck('match_id')
                ->toArray();

            $tournamentsCount = 0;
            if (!empty($matchIds)) {
                $tournamentsCount = DB::table('matches as m')
                    ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
                    ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
                    ->whereIn('m.id', $matchIds)
                    ->where('t.sport_id', $sport->sport_id)
                    ->distinct()
                    ->count('t.id');
            }

            $vnduprScore = $isGuest
                ? number_format((float) ($participant?->estimated_level ?? 0), 3)
                : $formattedScores['vndupr_score'];

            $sportsArray[] = [
                'sport_id'   => $sport->sport_id,
                'sport_icon' => $sport->relationLoaded('sport') ? optional($sport->sport)->icon : null,
                'sport_name' => $sport->relationLoaded('sport') ? optional($sport->sport)->name : null,
                'scores'     => array_merge($formattedScores, ['vndupr_score' => $vnduprScore]),
                'total_matches'     => $totalMatches,
                'total_tournaments' => $tournamentsCount,
                'total_prizes' => 0,
                'win_rate'    => $sport->getAttribute('win_rate') ?? 0,
                'performance' => $sport->getAttribute('performance') ?? 0,
            ];
        }

        return [
            // Root fields — khớp contract TeamMemberModel (Flutter)
            'id'                          => $this->id,
            'full_name'                   => $fullName,
            'avatar'                      => $avatarUrl,
            'sports'                      => $sportsArray,
            // Nested participant — cùng cấu trúc TournamentParticipantResource
            'tournament_participant'     => $participant
                                                ? (new ParticipantResource($participant))->withoutNestedUserSports()
                                                : null,
            // Tương thích web (CreateMatch.vue dùng member.name)
            'name'                        => $fullName,
            // Các field bổ sung (vẫn giữ cho web/frontend)
            'avatar_url'                  => $avatarUrl,
            'is_confirmed'                => (bool) ($participant?->is_confirmed ?? false),
            'is_invite_by_organizer'      => (bool) ($participant?->is_invite_by_organizer ?? false),
            'is_guest'                    => $isGuest,
            'guest_name'                  => $isGuest ? $participant?->guest_name : null,
            'guest_phone'                 => $isGuest ? $participant?->guest_phone : null,
            'guest_avatar'                => $isGuest ? $participant?->guest_avatar : null,
            'guarantor'                   => $participant?->relationLoaded('guarantor')
                                                    ? new UserListResource($participant->guarantor)
                                                    : null,
            'guarantor_user_id'          => $isGuest ? (int) $participant?->guarantor_user_id : null,
            'guarantor_name'             => $isGuest ? $participant?->guarantor?->full_name : null,
            'estimated_level'             => $isGuest ? (float) ($participant?->estimated_level ?? 0) : null,
            'is_pending_confirmation'     => $isGuest ? (bool) ($participant?->is_pending_confirmation ?? false) : null,
            'checked_in_at'              => $participant?->checked_in_at,
            'is_absent'                  => (bool) ($participant?->is_absent ?? false),
            // Gender
            'gender'                     => $this->gender,
            'gender_text'                => $this->gender_text,
        ];
    }
}
