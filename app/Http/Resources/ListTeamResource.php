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

                return [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'avatar' => $member->avatar_url,
                    'sports' => UserSportResource::collection($member->sports ?? []),
                    'tournament_participant' => $p ? new ParticipantResource($p) : null,
                ];
            }),
        ];
    }
}
