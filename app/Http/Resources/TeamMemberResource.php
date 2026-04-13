<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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

            $stats = User::getSportStats($this->id, $sport->sport_id);

            // Guest override vndupr_score, user giữ nguyên từ scores
            if ($isGuest) {
                $formattedScores['vndupr_score'] = number_format((float) ($participant?->estimated_level ?? 0), 3);
            }

            $sportsArray[] = [
                'sport_id'   => $sport->sport_id,
                'sport_icon' => $sport->relationLoaded('sport') ? optional($sport->sport)->icon : null,
                'sport_name' => $sport->relationLoaded('sport') ? optional($sport->sport)->name : null,
                'scores'     => $formattedScores,
                'total_matches'     => $stats['total_matches'],
                'total_tournaments' => $stats['total_tournaments'],
                'total_mini_tournaments' => $stats['total_mini_tournaments'],
                'total_prizes' => $stats['total_prizes'],
                'win_rate'    => $stats['win_rate'],
                'performance' => $stats['performance'],
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
