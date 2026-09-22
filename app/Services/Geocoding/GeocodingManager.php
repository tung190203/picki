<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Contracts\GeocodingProvider;
use InvalidArgumentException;

/**
 * Resolves the configured geocoding provider and delegates calls to it.
 */
class GeocodingManager
{
    /**
     * Registered providers map: driver name => FQCN.
     */
    protected array $providers = [
        'goong' => GoongProvider::class,
        'osm'   => OsmProvider::class,
    ];

    /**
     * Singleton instances.
     */
    protected array $instances = [];

    /**
     * Get the active provider instance (singleton).
     */
    public function provider(): GeocodingProvider
    {
        $driver = config('geocoder.driver', 'osm');

        if (! isset($this->providers[$driver])) {
            throw new InvalidArgumentException(
                "Geocoding driver [{$driver}] is not supported. Supported: " . implode(', ', array_keys($this->providers))
            );
        }

        if (! isset($this->instances[$driver])) {
            $this->instances[$driver] = new $this->providers[$driver]();
        }

        return $this->instances[$driver];
    }

    /**
     * Delegate search to the active provider.
     */
    public function search(string $query, array $options = []): array
    {
        return $this->provider()->search($query, $options);
    }

    /**
     * Delegate place detail to the active provider.
     */
    public function placeDetail(string $placeId): ?array
    {
        return $this->provider()->placeDetail($placeId);
    }
}
