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
     * Maximum allowed tier gap between players in the same match.
     * Used to validate cross-tier match combinations.
     *
     * Valid: Green + Yellow (gap=1), Yellow + Red (gap=1), Red + Purple (gap=1)
     * Invalid: Green + Red (gap=2), Yellow + Purple (gap=2), Green + Purple (gap=3)
     */
    public const MAX_TIER_GAP = 1;

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

    /**
     * Check if two tiers are adjacent (within MAX_TIER_GAP).
     */
    public static function isAdjacent(self $a, self $b): bool
    {
        return abs($a->priority() - $b->priority()) <= self::MAX_TIER_GAP;
    }

    /**
     * Calculate tier gap between two tiers.
     */
    public static function tierGap(self $a, self $b): int
    {
        return abs($a->priority() - $b->priority());
    }
}
