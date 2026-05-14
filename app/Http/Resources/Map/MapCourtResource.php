<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'address'         => $this->address,
            'number_of_yards' => $this->whenLoaded('competitionLocationYards', fn() => $this->competitionLocationYards->count()),
            'sport'           => $this->whenLoaded('sports', function () {
                $sport = $this->sports->first();
                return $sport ? [
                    'id'   => $sport->id,
                    'name' => $sport->name,
                    'icon' => $sport->icon,
                ] : null;
            }),
            'distance'     => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'  => 'court',
        ];
    }
}
