<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\Geocoding\GoongKeyResolver;

/**
 * Public controller for map provider config (no auth required).
 * Returns the map tiles key so frontend can initialize Goong JS at runtime.
 */
class MapProviderConfigController extends Controller
{
    public function __construct(
        protected GoongKeyResolver $keyResolver
    ) {}

    public function config()
    {
        $mapKey = $this->keyResolver->mapKey();

        if (! $mapKey) {
            return ResponseHelper::error('Map provider chua duoc cau hinh. Vui long lien he admin.', 503);
        }

        return ResponseHelper::success([
            'map_key'   => $mapKey,
            'style_url' => 'https://tiles.goong.io/assets/goong_map_web.json',
        ], 'Map provider config');
    }
}
