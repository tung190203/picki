<?php

namespace App\Services;

/**
 * @deprecated Use GeocodingManager directly or inject GeocodingProvider.
 *            This facade is kept for backward compatibility with existing
 *            controller callers (searchLocation, detailGooglePlace).
 */
class GeocodingService
{
    public function search(string $query): array
    {
        return app(\App\Services\Geocoding\GeocodingManager::class)->search($query);
    }

    /**
     * @deprecated Use placeDetail() instead. Kept for backward compat.
     */
    public function getGooglePlaceDetail(string $placeId): ?array
    {
        return app(\App\Services\Geocoding\GeocodingManager::class)->placeDetail($placeId);
    }
}
