<?php

namespace App\Http\Resources\Club;

/**
 * ClubSummaryResource — minimal club data for lightweight views.
 *
 * Used by: map markers, list items where only basic info is needed.
 * No queries, no computation — reads pre-set attributes only.
 */
class ClubSummaryResource extends ClubBaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logo_url,
            'status' => $this->status?->value ?? $this->status,
            'is_verified' => (bool) $this->is_verified,
            'is_public' => (bool) ($this->is_public ?? true),
            'created_by' => $this->created_by,
        ];
    }
}
