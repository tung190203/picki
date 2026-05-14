<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserSportResource;

class SearchPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'full_name'   => $this->full_name,
            'avatar_url'  => $this->avatar_url,
            'gender'      => $this->gender,
            'gender_text' => $this->gender_text ?? null,
            'age_group'   => $this->age_group ?? null,
            'visibility'  => $this->visibility,
            'address'     => $this->address ?? null,
            'is_online'  => (bool) $this->is_online,
            'is_verified' => (bool) $this->is_verified,
            'vn_rank'    => $this->vn_rank ?? null,
            'distance'    => $this->when(isset($this->distance), (int) round($this->distance)),
            'sports'      => $this->whenLoaded('sports', fn() =>
                UserSportResource::collection($this->sports)
            ),
            'clubs'       => $this->whenLoaded('clubs', fn() => $this->clubs->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'logo_url' => $c->logo_url,
            ])),
            'marker_type' => 'user',
        ];
    }
}
