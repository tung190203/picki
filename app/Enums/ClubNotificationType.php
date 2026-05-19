<?php

namespace App\Enums;

enum ClubNotificationType: int
{
    case General = 1;
    case Event = 2;
    case Finance = 3;
    case Member = 4;
    case Urgent = 5;

    public function slug(): string
    {
        return match ($this) {
            self::General => 'general',
            self::Event => 'event',
            self::Finance => 'finance',
            self::Member => 'member',
            self::Urgent => 'urgent',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::General => 'Thông báo chung',
            self::Event => 'Sự kiện',
            self::Finance => 'Tài chính',
            self::Member => 'Thành viên',
            self::Urgent => 'Khẩn cấp',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }
        return null;
    }
}
