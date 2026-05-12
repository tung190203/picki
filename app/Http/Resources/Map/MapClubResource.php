<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'logo_url' => $this->logo_url,
            'lat'      => $this->latitude,
            'lng'      => $this->longitude,
            'address'  => $this->address ?? $this->profile?->address,
            'is_public'    => (bool) ($this->is_public ?? true),
            'is_verified'  => (bool) $this->is_verified,
            'members_count' => (int) ($this->activeMembers_count ?? $this->activeMembers?->count() ?? 0),
            'is_joined'     => auth()->check()
                ? ($this->relationLoaded('activeMembers')
                    ? $this->activeMembers->contains('user_id', auth()->id())
                    : false)
                : false,
            'distance' => $this->when(isset($this->distance), (int) round($this->distance)),
            'marker_type' => 'club',
        ];
    }
}
