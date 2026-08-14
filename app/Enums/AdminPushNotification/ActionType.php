<?php

namespace App\Enums\AdminPushNotification;

enum ActionType: string
{
    case NONE = 'NONE';
    case MATCH = 'MATCH';
    case TOURNAMENT = 'TOURNAMENT';
    case CLUB = 'CLUB';

    public function label(): string
    {
        return match($this) {
            self::NONE => 'Không có',
            self::MATCH => 'Trận đấu',
            self::TOURNAMENT => 'Giải đấu',
            self::CLUB => 'Câu lạc bộ',
        };
    }

    public function modelClass(): ?string
    {
        return match($this) {
            self::NONE => null,
            self::MATCH => \App\Models\QuickMatch::class,
            self::TOURNAMENT => \App\Models\Tournament::class,
            self::CLUB => \App\Models\Club\Club::class,
        };
    }

    public function table(): ?string
    {
        return match($this) {
            self::NONE => null,
            self::MATCH => 'quick_matches',
            self::TOURNAMENT => 'tournaments',
            self::CLUB => 'clubs',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function pattern(): string
    {
        return implode(',', self::values());
    }
}