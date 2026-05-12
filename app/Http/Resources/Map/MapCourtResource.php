<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'address'      => $this->address,
            'number_of_yards' => $this->number_of_yards,
            'sport'        => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'distance' => $this->when(isset($this->distance), (int) round($this->distance)),
            'marker_type' => 'court',
        ];
    }
}
