<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        // Ensure sports relation loaded (cascade từ hydrator hoặc từ query gốc)
        $sportsLoaded = $this->relationLoaded('sports')
            ? $this->sports
            : collect();

        return [
            // Root fields — khớp contract TeamMemberModel (Flutter)
            'id'                          => $this->id,
            'full_name'                   => $fullName,
            'avatar'                      => $avatarUrl,
            'sports'                      => UserSportResource::collection($sportsLoaded),
            // Nested participant — cùng cấu trúc TournamentParticipantResource
            'tournament_participant'       => $participant
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
