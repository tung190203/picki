<?php

namespace App\Enums;

enum BadgeType: string
{
    case VERIFIED = 'VERIFIED';
    case ANCHOR = 'ANCHOR';
    case CHAMPION = 'CHAMPION';
    case PICKI = 'PICKI';

    public function label(): string
    {
        return match ($this) {
            self::VERIFIED => 'Verified',
            self::ANCHOR => 'Anchor',
            self::CHAMPION => 'Champion',
            self::PICKI => 'Picki',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::VERIFIED => 'User with verified account',
            self::ANCHOR => 'User with verified score',
            self::CHAMPION => 'Champion badge',
            self::PICKI => 'Picki badge',
        };
    }
}
