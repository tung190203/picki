<?php

namespace App\Enums\AdminPushNotification;

enum SendType: string
{
    case IMMEDIATE = 'IMMEDIATE';
    case SCHEDULED = 'SCHEDULED';

    public function label(): string
    {
        return match($this) {
            self::IMMEDIATE => 'Gửi ngay',
            self::SCHEDULED => 'Lên lịch',
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