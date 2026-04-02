<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'tournament_type_id' => $this->tournament_type_id,
            'avatar' => $this->avatar,
            'members' => $this->members->map(function ($member) {
                /** @var \App\Models\Participant|null $p */
                $p = $member->relationLoaded('tournamentParticipant')
                    ? $member->tournamentParticipant
                    : null;

                $isGuest = $p?->is_guest;

                return [
                    'id' => $member->id,
                    'full_name' => $isGuest ? ($p->guest_name ?? $member->full_name) : $member->full_name,
                    'avatar_url' => $isGuest ? ($p->guest_avatar ?? $member->avatar_url) : $member->avatar_url,
                    'is_guest' => $isGuest,
                    'sports' => UserSportResource::collection($member->sports ?? []),
                    'tournament_participant' => $p ? new ParticipantResource($p) : null,
                ];
            }),
        ];
    }
}
