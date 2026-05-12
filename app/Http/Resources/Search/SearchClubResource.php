<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->whenLoaded('profile', fn() => $this->profile?->cover_image_url),
            'address'  => $this->address ?? $this->whenLoaded('profile', fn() => $this->profile?->address),
            'lat'      => $this->latitude,
            'lng'      => $this->longitude,
            'is_public'    => (bool) ($this->is_public ?? true),
            'is_verified'  => (bool) $this->is_verified,
            'members_count' => (int) ($this->activeMembers_count ?? $this->activeMembers?->count() ?? 0),
            'description' => $this->whenLoaded('profile', fn() => $this->profile?->description),
            'is_member' => (bool) ($this->is_member ?? false),
            'is_admin' => (bool) ($this->is_admin ?? false),
            'has_pending_request' => (bool) ($this->has_pending_request ?? false),
            'distance' => $this->when(isset($this->distance), (int) round($this->distance)),
            'marker_type' => 'club',
        ];
    }
}
