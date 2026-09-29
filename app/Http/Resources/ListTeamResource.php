<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'tournament_type_id' => $this->tournament_type_id,
            'avatar' => $this->avatar,
            'members' => \App\Http\Resources\TeamMemberResource::collection(
                collect($this->members ?? [])->merge(
                    ($this->guestMembers ?? collect())->map(fn ($tm) => $tm->participant)->filter()
                )
            ),
        ];
    }
}
