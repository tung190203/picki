<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'image'        => $this->image,
            'address'      => $this->address,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'phone'        => $this->phone,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'number_of_yards' => $this->number_of_yards,
            'sports'       => $this->whenLoaded('sports', fn() => $this->sports->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'icon' => $s->icon,
            ])),
            'distance'     => $this->when(isset($this->distance), (int) round($this->distance)),
            'marker_type'  => 'court',
        ];
    }
}
