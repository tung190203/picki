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

        $totalMatches = $user->total_matches ?? 0;
        $kValue = match (true) {
            $totalMatches < 10 => 1.0,
            $totalMatches < 50 => 0.6,
            default => 0.3,
        };

        return $kValue;
    }

    public static function calculateNewRating(float $currentRating, float $expected, float $actual, User $user): float
    {
        return $currentRating + self::getKFactor($user) * ($actual - $expected);
    }
}
