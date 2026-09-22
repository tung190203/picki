<?php

namespace App\Services\Geocoding;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves Goong API keys with DB-first priority and env fallback.
 *
 * Priority: system_settings DB row > env variable
 * Cache: 60 seconds TTL (auto-expires, manual invalidation on admin save).
 */
class GoongKeyResolver
{
    protected const CACHE_TTL_SECONDS = 60;
    protected const CACHE_KEY_API = 'goong.api_key';
    protected const CACHE_KEY_MAP = 'goong.map_key';

    /**
     * Get the REST API key (server-side only, never sent to browser).
     */
    public function apiKey(): ?string
    {
        return Cache::remember(self::CACHE_KEY_API, self::CACHE_TTL_SECONDS, function () {
            $row = SystemSetting::where('key', self::CACHE_KEY_API)->first();
            $dbValue = $row?->value;

            if ($dbValue !== null && $dbValue !== '') {
                return $dbValue;
            }

            // Fallback to env
            return env('GOONG_API_KEY');
        });
    }

    /**
     * Get the map tiles key (public, sent to browser).
     */
    public function mapKey(): ?string
    {
        return Cache::remember(self::CACHE_KEY_MAP, self::CACHE_TTL_SECONDS, function () {
            $row = SystemSetting::where('key', self::CACHE_KEY_MAP)->first();
            $dbValue = $row?->value;

            if ($dbValue !== null && $dbValue !== '') {
                return $dbValue;
            }

            // Fallback to env
            return env('GOONG_MAP_KEY');
        });
    }

    /**
     * Invalidate both cache entries.
     * Call this when admin saves new keys so new values take effect immediately.
     */
    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY_API);
        Cache::forget(self::CACHE_KEY_MAP);
    }
}
