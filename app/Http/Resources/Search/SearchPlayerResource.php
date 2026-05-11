<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'full_name'   => $this->full_name,
            'avatar_url'   => $this->avatar_url,
            'gender'      => $this->gender,
            'is_online'   => (bool) $this->is_online,
            'is_verified' => (bool) $this->is_verified,
            'vn_rank'     => $this->vn_rank ?? null,
            'distance'    => $this->when(isset($this->distance), round($this->distance, 1)),
            'sports'      => $this->whenLoaded('sports', fn() => $this->sports->map(fn($s) => [
                'id'   => $s->sport_id,
                'name' => $s->sport?->name,
                'icon' => $s->sport?->icon,
            ])),
            'clubs'       => $this->whenLoaded('clubs', fn() => $this->clubs->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'logo_url' => $c->logo_url,
            ])),
            'marker_type' => 'user',
        ];
    }
}
