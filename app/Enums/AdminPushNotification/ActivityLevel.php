<?php

namespace App\Enums\AdminPushNotification;

enum ActivityLevel: string
{
    case HOT = 'HOT';
    case WARM = 'WARM';
    case COLD = 'COLD';

    public function label(): string
    {
        return match($this) {
            self::HOT => 'Nóng (HOT)',
            self::WARM => 'Ấm (WARM)',
            self::COLD => 'Lạnh (COLD)',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::HOT => 'Hoạt động trong vòng 7 ngày qua',
            self::WARM => 'Hoạt động từ 7 đến 30 ngày qua',
            self::COLD => 'Không hoạt động hơn 30 ngày hoặc chưa từng hoạt động',
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