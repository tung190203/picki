<?php

namespace App\Http\Resources\Club;

use App\Http\Resources\CompetitionLocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource cho 1 sân nhà của CLB.
 *
 * Trả nested CompetitionLocationResource thay vì lẻ các field riêng lẻ,
 * để client nhận đầy đủ thông tin bao gồm image, location, facilities...
 */
class ClubHomeCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        $pivot = $this->pivot ?? null;

        // Load relations nếu chưa loaded (cho trường hợp dùng trong collection)
        if (!$this->relationLoaded('location') && !$this->relationLoaded('sports') && !$this->relationLoaded('facilities')) {
            $this->loadMissing(['location', 'sports', 'facilities']);
        }

        return [
            'id' => $this->id,
            'position' => (int) ($pivot->position ?? 0),
            'distance_km' => $pivot && $pivot->distance_km !== null ? (float) $pivot->distance_km : null,
            'events_hosted_count' => (int) ($pivot->events_hosted_count ?? 0),
            'location' => CompetitionLocationResource::make($this->resource)->resolve($request),
        ];
    }
}
