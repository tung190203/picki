<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;

/**
 * OpenStreetMap / Nominatim provider.
 */
class OsmProvider implements GeocodingProvider
{
    protected int $timeout = 10;

    public function search(string $query, array $options = []): array
    {
        $url = config('geocoder.osm.base_url', 'https://nominatim.openstreetmap.org/search');

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'PickiApp/1.0',
                ])->get($url, [
                    'format' => 'json',
                    'q' => $query,
                    'limit' => $options['limit'] ?? 5,
                    'addressdetails' => 1,
                    'countrycodes' => config('geocoder.country_code', 'vn'),
                ])->json();
        } catch (\Exception $e) {
            report($e);
            return [];
        }

        return collect($response ?? [])->map(fn($item) => [
            'id'          => $item['place_id'] ?? $item['osm_id'],
            'description' => $item['display_name'],
            'lat'         => $item['lat'],
            'lng'         => $item['lon'],
        ])->all();
    }

    public function placeDetail(string $placeId): ?array
    {
        // OSM Nominatim uses place_id differently; not implemented here.
        // This provider focuses on autocomplete use-cases.
        return null;
    }
}
