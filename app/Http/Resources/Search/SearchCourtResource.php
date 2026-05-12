<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'competition_location' => [
                'id'           => $this->id,
                'name'         => $this->name,
                'image'        => $this->image,
                'address'      => $this->address,
                'latitude'     => $this->latitude,
                'longitude'    => $this->longitude,
                'phone'        => $this->phone,
                'opening_time' => $this->opening_time,
                'closing_time' => $this->closing_time,
                'number_of_yards' => $this->whenLoaded('competitionLocationYards', fn() => $this->competitionLocationYards->count()),
                'sports'       => $this->whenLoaded('sports', fn() => $this->sports->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                    'icon' => $s->icon,
                ])),
                'facilities'   => $this->whenLoaded('facilities', fn() => $this->facilities->map(fn($f) => [
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'description' => $f->description,
                ])),
                'distance'     => $this->when(isset($this->distance), (int) round($this->distance)),
                'marker_type'  => 'court',
            ],
        ];
    }
}
