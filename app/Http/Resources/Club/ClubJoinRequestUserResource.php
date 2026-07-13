<?php

namespace App\Http\Resources\Club;

use App\Http\Resources\UserSportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubJoinRequestUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'sports' => $this->relationLoaded('sports')
                ? UserSportResource::collection($this->sports)
                : [],
        ];
    }
}