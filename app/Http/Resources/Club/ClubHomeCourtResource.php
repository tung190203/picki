<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource cho 1 sân nhà của CLB.
 *
 * Resource nhận:
 *  - Eloquent model (CompetitionLocation) + pivot: dùng cho cập nhật
 *  - Hoặc array đã enrich từ ClubHomeCourtService (distance_km, events_hosted_count đã tính sẵn)
 *
 * Khi là array, 2 field computed sẽ lấy trực tiếp từ array.
 */
class ClubHomeCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        $pivot = $this->pivot ?? null;

        return [
            'id' => (int) $this->id,
            'competition_location_id' => (int) $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'position' => (int) ($pivot->position ?? 0),
            'distance_km' => $pivot && $pivot->distance_km !== null ? (float) $pivot->distance_km : null,
            'events_hosted_count' => (int) ($pivot->events_hosted_count ?? 0),
        ];
    }
}
