<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'full_name'  => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'lat'        => $this->latitude,
            'lng'        => $this->longitude,
            'gender'     => $this->gender,
            'is_online'  => $this->is_online,
            'is_verified'=> (bool) $this->is_verified,
            'distance'   => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type' => 'user',
        ];
    }
}
