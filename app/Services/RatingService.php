<?php

namespace App\Services;

use App\Models\User;

class RatingService
{
    public static function getKFactor(User $user): float
    {
        if ($user->is_anchor) {
            return 0.1;
        }

        $anchored = $user->total_matches_has_anchor ?? 0;
        $kValue = match (true) {
            $anchored < 10 => 1.0,
            $anchored < 50 => 0.6,
            default => 0.3,
        };

        return $kValue;
    }

    public static function calculateNewRating(float $currentRating, float $expected, float $actual, User $user): float
    {
        return $currentRating + self::getKFactor($user) * ($actual - $expected);
    }
}
