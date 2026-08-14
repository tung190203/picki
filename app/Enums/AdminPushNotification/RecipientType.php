<?php

namespace App\Enums\AdminPushNotification;

enum RecipientType: string
{
    case ALL = 'ALL';
    case CLUB = 'CLUB';
    case ACTIVITY = 'ACTIVITY';
    case USERS = 'USERS';

    public function label(): string
    {
        return match($this) {
            self::ALL => 'Tất cả người dùng',
            self::CLUB => 'Theo câu lạc bộ',
            self::ACTIVITY => 'Theo mức độ hoạt động',
            self::USERS => 'Theo danh sách người dùng',
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