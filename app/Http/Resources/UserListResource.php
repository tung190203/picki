<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
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
            'full_name' => $this->full_name,
            'visibility' => $this->visibility,
            'avatar_url' => $this->avatar_url,
            'thumbnail' => $this->thumbnail,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'play_times' => UserPlayTimeResource::collection($this->whenLoaded('playTimes')),
            'sports' => UserSportResource::collection($this->whenLoaded('sports')) ?? [],
            'is_manager' => $this->whenPivotLoaded('club_members', fn() => (bool)$this->pivot->is_manager, false),
            'rank_in_club' => $this->whenPivotLoaded(
                'club_members',
                fn () => $this->pivot->rank_in_club ?? null
            ),
            'is_anchor' => (bool) $this->is_anchor,
            'is_verify' => (bool) ($this->total_matches_has_anchor >= 10),
            'is_guest'  => (bool) $this->is_guest,
        ];
    }
}
