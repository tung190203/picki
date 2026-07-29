<?php

namespace App\Enums;

enum MatchTier: string
{
    case A = 'A';
    case B = 'B';

    public function label(): string
    {
        return match($this) {
            self::A => 'Hạng A',
            self::B => 'Hạng B',
        };
    }

    public static function fromRating(?float $rating): self
    {
        if ($rating === null) {
            return self::B;
        }

        return $rating >= 2.0 ? self::A : self::B;
    }

    public static function fromRatingDetailed(float $rating): self
    {
        return match(true) {
            $rating >= 2.0 => self::A,
            default => self::B,
        };
    }
}
