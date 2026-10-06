<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubGuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastAt = $this->computed_last_participated_at ?? $this->last_played_at;

        return [
            'user_id' => $this->user_id,
            'play_count' => (int) ($this->computed_event_count ?? $this->play_count ?? 0),
            'days_since_last_play' => $this->days_since_last_play,
            'is_invited' => (bool) $this->is_invited,
            'is_followed' => (bool) ($this->is_followed ?? false),
            'last_played_at' => $lastAt ? $lastAt->toISOString() : null,
            'user' => $this->whenLoaded('user', function () {
                return new \App\Http\Resources\UserResource($this->user);
            }),
        ];
    }
}
