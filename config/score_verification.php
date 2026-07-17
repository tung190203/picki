<?php

return [
    'max_difference' => env('SCORE_VERIFICATION_MAX_DIFFERENCE', 0.5),
    'valid_score_min' => 0,
    'valid_score_max' => 8,
    'allowed_image_types' => ['jpg', 'jpeg', 'png'],
    'max_image_size_mb' => 10,
];
