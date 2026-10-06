<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubHomeCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'competition_location_id' => $this->competition_location_id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'position' => (int) ($this->pivot->position ?? 0),
            'distance_km' => $this->pivot->distance_km !== null ? (float) $this->pivot->distance_km : null,
            'events_hosted_count' => (int) ($this->pivot->events_hosted_count ?? 0),
        ];
    }
}
