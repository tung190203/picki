<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Geocoding\GoongKeyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the Geocoding abstraction layer.
 *
 * Covers:
 * - GoongProvider: search + placeDetail (HTTP mocking)
 * - GoongKeyResolver: DB-first priority + env fallback + cache
 * - GeocodingManager: driver resolution
 * - OsmProvider: basic search
 */
class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------------
    // GoongProvider — search
    // ----------------------------------------------------------------

    public function test_search_goong_returns_predictions(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'test-api-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        Http::fake([
            'rsapi.goong.io/v2/place/autocomplete*' => Http::response([
                'predictions' => [
                    [
                        'place_id' => 'abc123',
                        'structured_formatting' => [
                            'main_text' => '91 Trung Kính',
                            'secondary_text' => 'Trung Hòa, Cầu Giấy, Hà Nội',
                        ],
                    ],
                    [
                        'place_id' => 'def456',
                        'structured_formatting' => [
                            'main_text' => '92 Trung Kính',
                            'secondary_text' => 'Trung Hòa, Cầu Giấy, Hà Nội',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $results = $manager->search('trung kinh');

        $this->assertCount(2, $results);
        $this->assertEquals('abc123', $results[0]['place_id']);
        $this->assertStringContainsString('91 Trung Kính', $results[0]['description']);
        $this->assertStringContainsString('Trung Hòa', $results[0]['description']);
    }

    public function test_search_goong_includes_api_key_in_request(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'my-secret-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        Http::fake([
            'rsapi.goong.io/*' => function ($request) {
                $query = $request->query();
                $this->assertArrayHasKey('api_key', $query);
                $this->assertEquals('my-secret-key', $query['api_key']);
                $this->assertArrayHasKey('input', $query);

                return Http::response(['predictions' => []], 200);
            },
        ]);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $manager->search('test');
    }

    public function test_search_goong_returns_empty_when_no_api_key(): void
    {
        // DB has no key, env has no key
        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $results = $manager->search('test');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ----------------------------------------------------------------
    // GoongProvider — placeDetail
    // ----------------------------------------------------------------

    public function test_place_detail_goong_returns_lat_lng_address(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'test-api-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        Http::fake([
            'rsapi.goong.io/Geocode*' => Http::response([
                'results' => [
                    [
                        'geometry' => [
                            'location' => ['lat' => 21.0137, 'lng' => 105.7982],
                        ],
                        'formatted_address' => '91 Trung Kính, Trung Hòa, Cầu Giấy, Hà Nội',
                    ],
                ],
            ], 200),
        ]);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $result = $manager->placeDetail('abc123');

        $this->assertNotNull($result);
        $this->assertEquals(21.0137, $result['lat']);
        $this->assertEquals(105.7982, $result['lng']);
        $this->assertStringContainsString('91 Trung Kính', $result['address']);
    }

    public function test_place_detail_goong_returns_null_when_no_results(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'test-api-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        Http::fake([
            'rsapi.goong.io/Geocode*' => Http::response(['results' => []], 200),
        ]);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $result = $manager->placeDetail('nonexistent');

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // GoongKeyResolver — priority
    // ----------------------------------------------------------------

    public function test_resolver_prefers_db_over_env(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'db-value',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        config(['services.goong_api_key_fallback' => 'env-value']);

        // Manually set env-like value
        putenv('GOONG_API_KEY=env-value');

        $resolver = app(GoongKeyResolver::class);
        // Force cache miss
        $resolver->forgetCache();

        $this->assertEquals('db-value', $resolver->apiKey());

        putenv('GOONG_API_KEY'); // cleanup
    }

    public function test_resolver_falls_back_to_env_when_db_null(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => null,
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        putenv('GOONG_API_KEY=fallback-from-env');

        $resolver = app(GoongKeyResolver::class);
        $resolver->forgetCache();

        $this->assertEquals('fallback-from-env', $resolver->apiKey());

        putenv('GOONG_API_KEY'); // cleanup
    }

    public function test_resolver_forget_cache_clears_both_keys(): void
    {
        SystemSetting::create([
            'key' => 'goong.api_key',
            'value' => 'api-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);
        SystemSetting::create([
            'key' => 'goong.map_key',
            'value' => 'map-key',
            'type' => 'string',
            'group' => 'map_provider',
        ]);

        $resolver = app(GoongKeyResolver::class);

        // Populate cache
        $this->assertEquals('api-key', $resolver->apiKey());
        $this->assertEquals('map-key', $resolver->mapKey());

        // Forget
        $resolver->forgetCache();

        // Cache should be empty, next call reads from DB
        SystemSetting::where('key', 'goong.api_key')->update(['value' => 'new-api-key']);

        $this->assertEquals('new-api-key', $resolver->apiKey());
    }

    // ----------------------------------------------------------------
    // GeocodingManager — driver resolution
    // ----------------------------------------------------------------

    public function test_manager_resolves_goong_driver(): void
    {
        config(['geocoder.driver' => 'goong']);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);

        // Should not throw
        $provider = $manager->provider();
        $this->assertInstanceOf(\App\Services\Geocoding\GoongProvider::class, $provider);
    }

    public function test_manager_resolves_osm_driver(): void
    {
        config(['geocoder.driver' => 'osm']);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);

        $provider = $manager->provider();
        $this->assertInstanceOf(\App\Services\Geocoding\OsmProvider::class, $provider);
    }

    public function test_manager_throws_on_unknown_driver(): void
    {
        config(['geocoder.driver' => 'nonexistent']);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nonexistent');

        $manager->provider();
    }

    // ----------------------------------------------------------------
    // OsmProvider — basic search
    // ----------------------------------------------------------------

    public function test_osm_search_returns_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'place_id' => 123456,
                    'display_name' => 'Hanoi, Vietnam',
                    'lat' => '21.0285',
                    'lon' => '105.8542',
                ],
            ], 200),
        ]);

        config(['geocoder.driver' => 'osm']);

        $manager = app(\App\Services\Geocoding\GeocodingManager::class);
        $results = $manager->search('hanoi');

        $this->assertCount(1, $results);
        $this->assertEquals('Hanoi, Vietnam', $results[0]['description']);
        $this->assertEquals('21.0285', $results[0]['lat']);
        $this->assertEquals('105.8542', $results[0]['lng']);
    }
}
