<?php

namespace App\Enums;

/**
 * Player tier based on color ring and skill level.
 * 
 * Priority: Purple (highest) > Red > Yellow > Green (lowest)
 * 
 * Used for:
 * - Match quality assessment (prefer_high_tier_match)
 * - Tier scoring in balance calculations
 * - Tier starvation prevention
 */
enum PlayerTier: string
{
    case Purple = 'purple';
    case Red = 'red';
    case Yellow = 'yellow';
    case Green = 'green';

    /**
     * Get numeric priority for sorting (higher = stronger).
     */
    public function priority(): int
    {
        return match ($this) {
            self::Purple => 4,
            self::Red => 3,
            self::Yellow => 2,
            self::Green => 1,
        };
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Purple => 'Vòng Tím',
            self::Red => 'Vòng Đỏ',
            self::Yellow => 'Vòng Vàng',
            self::Green => 'Vòng Xanh',
        };
    }

    /**
     * Get numeric score for balance calculations.
     */
    public function score(): float
    {
        return match ($this) {
            self::Purple => 4.0,
            self::Red => 3.0,
            self::Yellow => 2.0,
            self::Green => 1.0,
        };
    }

    /**
     * Create from string value.
     */
    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
