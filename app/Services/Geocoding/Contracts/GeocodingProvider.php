<?php

namespace App\Services\Geocoding\Contracts;

/**
 * Contract for geocoding / places providers.
 *
 * Implementations must be registered in GeocodingManager.
 */
interface GeocodingProvider
{
    /**
     * Search for places / addresses (autocomplete).
     *
     * @param  string  $query  Search keyword
     * @param  array   $options  e.g. ['location' => 'lat,lng', 'limit' => 5]
     * @return array  List of matches: [{place_id, description, lat?, lng?}]
     */
    public function search(string $query, array $options = []): array;

    /**
     * Get place details (lat/lng/address) by place ID.
     *
     * @param  string  $placeId  Provider-specific place ID
     * @return array|null  {lat, lng, address} or null on failure
     */
    public function placeDetail(string $placeId): ?array;
}
