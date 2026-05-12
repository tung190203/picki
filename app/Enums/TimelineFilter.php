<?php

namespace App\Enums;

enum TimelineFilter: string
{
    case ALL = 'all';
    case MINE = 'mine';
    case TODAY = 'today';
    case THIS_WEEK = 'this_week';
    case THIS_MONTH = 'this_month';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Tất cả',
            self::MINE => 'Của tôi',
            self::TODAY => 'Hôm nay',
            self::THIS_WEEK => 'Tuần này',
            self::THIS_MONTH => 'Tháng này',
        };
    }

    public function badge(): ?string
    {
        return match ($this) {
            self::TODAY => 'Hôm nay',
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
