<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo_url' => $this->logo_url,
            'status' => $this->status,
            'is_public' => (bool) ($this->is_public ?? true),
            'is_verified' => (bool) $this->is_verified,
            'created_by' => $this->created_by,
            'quantity_members' => $this->when(isset($this->members_count), (int) $this->members_count, 0),
            'cover_image_url' => $this->whenLoaded('profile', fn () => $this->profile?->cover_image_url),
            'profile' => $this->whenLoaded('profile', fn () => [
                'description' => $this->profile?->description,
            ]),
            'is_member' => (bool) ($this->is_member ?? false),
            'is_admin' => (bool) ($this->is_admin ?? false),
            'has_pending_request' => (bool) ($this->has_pending_request ?? false),
            'has_invitation' => (bool) ($this->has_invitation ?? false),
            'created_at' => $this->created_at?->toISOString(),
            'distance' => $this->when(isset($this->distance), $this->distance),
        ];
    }
}
