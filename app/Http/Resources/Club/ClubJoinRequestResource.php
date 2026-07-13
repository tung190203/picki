<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubJoinRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'position' => $this->position,
            'membership_status' => $this->membership_status,
            'status' => $this->status,
            'message' => $this->when($this->membership_status?->value === 'pending', $this->message),
            'joined_at' => $this->joined_at?->toISOString(),
            'user' => new ClubJoinRequestUserResource($this->whenLoaded('user')),
        ];
    }
}