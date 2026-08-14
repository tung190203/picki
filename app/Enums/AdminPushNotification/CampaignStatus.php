<?php

namespace App\Enums\AdminPushNotification;

enum CampaignStatus: string
{
    case DRAFT = 'DRAFT';
    case SCHEDULED = 'SCHEDULED';
    case PROCESSING = 'PROCESSING';
    case SENT = 'SENT';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Bản nháp',
            self::SCHEDULED => 'Đã lên lịch',
            self::PROCESSING => 'Đang gửi',
            self::SENT => 'Đã gửi',
            self::PARTIAL => 'Gửi một phần',
            self::FAILED => 'Gửi thất bại',
            self::CANCELLED => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT, self::CANCELLED => 'gray',
            self::SCHEDULED => 'blue',
            self::PROCESSING => 'yellow',
            self::SENT => 'green',
            self::PARTIAL => 'orange',
            self::FAILED => 'red',
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