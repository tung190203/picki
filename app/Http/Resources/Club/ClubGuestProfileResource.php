<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubGuestProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'estimated_level' => $this->estimated_level !== null ? (float) $this->estimated_level : null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'full_name' => $this->user->full_name,
                    'phone' => $this->user->phone,
                    'avatar_url' => $this->user->avatar_url,
                    'is_guest' => (bool) $this->user->is_guest,
                ];
            }),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
