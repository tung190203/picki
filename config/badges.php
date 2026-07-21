<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Badge Priority Configuration
    |--------------------------------------------------------------------------
    |
    | Lower number = higher priority. Primary badge is the one with the
    | lowest priority number that the user has earned.
    |
    */
    'priority' => [
        \App\Enums\BadgeType::VERIFIED->value => 1,
        \App\Enums\BadgeType::CHAMPION->value => 2,
        \App\Enums\BadgeType::ANCHOR->value => 3,
        \App\Enums\BadgeType::PICKI->value => 4,
    ],
];
