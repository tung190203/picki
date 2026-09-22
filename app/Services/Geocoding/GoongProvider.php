<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Contracts\GeocodingProvider;
use Illuminate\Support\Facades\Http;

/**
 * Goong.io provider.
 *
 * Docs: https://docs.goong.io
 */
class GoongProvider implements GeocodingProvider
{
    protected int $timeout = 10;

    protected string $baseUrl = 'https://rsapi.goong.io';

    public function search(string $query, array $options = []): array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return [];
        }

        $params = [
            'input' => $query,
            'api_key' => $apiKey,
            'limit' => $options['limit'] ?? 5,
        ];

        // Bias results to Vietnam by passing a center point.
        // Use config bounds or fallback to Hanoi center.
        $centerLat = config('geocoder.goong.vietnam_center_lat', '21.0285');
        $centerLng = config('geocoder.goong.vietnam_center_lng', '105.8542');
        $params['location'] = "{$centerLat},{$centerLng}";

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/v2/place/autocomplete", $params)
                ->json();
        } catch (\Exception $e) {
            report($e);
            return [];
        }

        $predictions = $response['predictions'] ?? [];

        return collect($predictions)->map(function ($item) {
            $mainText = $item['structured_formatting']['main_text'] ?? '';
            $secondaryText = $item['structured_formatting']['secondary_text'] ?? '';

            return [
                'place_id'    => $item['place_id'] ?? null,
                'description' => $mainText . ($secondaryText ? ', ' . $secondaryText : ''),
                'lat'         => null,
                'lng'         => null,
            ];
        })->all();
    }

    public function placeDetail(string $placeId): ?array
    {
        $apiKey = $this->getApiKey();
        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/Geocode", [
                    'place_id' => $placeId,
                    'api_key'  => $apiKey,
                ])->json();
        } catch (\Exception $e) {
            report($e);
            return null;
        }

        $results = $response['results'] ?? [];
        if (empty($results)) {
            return null;
        }

        $first = $results[0];
        $location = $first['geometry']['location'] ?? null;

        if (! $location) {
            return null;
        }

        return [
            'lat'     => $location['lat'],
            'lng'     => $location['lng'],
            'address' => $first['formatted_address'] ?? null,
        ];
    }

    protected function getApiKey(): ?string
    {
        // Delegate to GoongKeyResolver so DB value takes priority over env.
        return app(GoongKeyResolver::class)->apiKey();
    }
}
