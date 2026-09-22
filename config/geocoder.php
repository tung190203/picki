<?php

return [
    'driver' => env('GEOCODER_DRIVER', 'goong'),

    'goong' => [
        'base_url'            => env('GOONG_API_URL', 'https://rsapi.goong.io'),
        'api_key'             => env('GOONG_API_KEY'),
        'autocomplete_path'   => '/v2/place/autocomplete',
        'detail_path'         => '/Geocode',
        // Vietnam center for location bias in autocomplete
        'vietnam_center_lat'  => env('GOONG_VIETNAM_CENTER_LAT', '21.0285'),
        'vietnam_center_lng'  => env('GOONG_VIETNAM_CENTER_LNG', '105.8542'),
    ],

    'osm' => [
        'base_url'    => env('OSM_BASE_URL', 'https://nominatim.openstreetmap.org/search'),
        'country_code' => env('OSM_COUNTRY_CODE', 'vn'),
    ],

    // Vietnam bounding box for location-based filtering (used by some providers)
    'vietnam_bounds' => [
        'min_lat' => 8.179066,
        'max_lat' => 23.393395,
        'min_lng' => 102.14441,
        'max_lng' => 109.46918,
    ],
];
