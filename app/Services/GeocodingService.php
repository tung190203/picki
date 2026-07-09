<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    protected int $timeout = 10;

    public function search(string $query): array
    {
        $driver = config('geocoder.driver', 'osm');

        return match ($driver) {
            'google' => $this->googleSearch($query),
            'osm'    => $this->osmSearch($query),
            default  => [],
        };
    }

    /**
     * Google Autocomplete (Places API New)
     * Giới hạn kết quả trong Việt Nam
     * OPTIMIZED: Added timeout to prevent blocking requests
     */
    protected function googleSearch(string $query): array
    {
        $apiKey = config('geocoder.google.key');
        $url = config('geocoder.google.autocomplete_url');

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type'     => 'application/json',
                    'X-Goog-Api-Key'   => $apiKey,
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.place,'
                        .'suggestions.placePrediction.structuredFormat,'
                        .'suggestions.placePrediction.text',
                ])->post($url, [
                    'input' => $query,
                    'locationRestriction' => [
                        'rectangle' => [
                            'low' => [
                                'latitude'  => 8.179066,
                                'longitude' => 102.14441,
                            ],
                            'high' => [
                                'latitude'  => 23.393395,
                                'longitude' => 109.46918,
                            ],
                        ],
                    ],
                ])->json();
        } catch (\Exception $e) {
            report($e);
            return [];
        }

        $suggestions = $response['suggestions'] ?? [];

        return collect($suggestions)->map(function ($item) {
            $p = $item['placePrediction'] ?? [];

            return [
                'place_id'    => $p['place'] ?? $p['placeId'] ?? null,
                'description' =>
                    ($p['structuredFormat']['mainText']['text'] ?? '') . ', ' .
                    ($p['structuredFormat']['secondaryText']['text'] ?? ''),
                'lat' => null,
                'lng' => null,
            ];
        })->all();
    }

    /**
     * Google Place Details (Places API New)
     * Lấy lat/lng theo place_id
     * OPTIMIZED: Added timeout to prevent blocking requests
     */
    public function getGooglePlaceDetail(string $placeId): ?array
    {
        $apiKey = config('geocoder.google.key');
        $cleanId = str_replace('places/', '', $placeId);
        $url = config('geocoder.google.details_url') . $cleanId;

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $apiKey,
                    'X-Goog-FieldMask' => 'id,location,formattedAddress,displayName',
                ])->get($url)->json();
        } catch (\Exception $e) {
            report($e);
            return null;
        }

        if (!isset($response['location'])) {
            return null;
        }

        return [
            'lat'     => $response['location']['latitude'],
            'lng'     => $response['location']['longitude'],
            'address' => $response['formattedAddress']
                ?? $response['displayName']['text']
                ?? null,
        ];
    }

    /**
     * OSM API — Giới hạn tìm kiếm trong Việt Nam
     * OPTIMIZED: Added timeout to prevent blocking requests
     */
    protected function osmSearch(string $query): array
    {
        $url = config('geocoder.osm.base_url', 'https://nominatim.openstreetmap.org/search');

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'MyApp/1.0',
                ])->get($url, [
                    'format' => 'json',
                    'q' => $query,
                    'limit' => 5,
                    'addressdetails' => 1,
                    'countrycodes' => 'vn',
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
}
