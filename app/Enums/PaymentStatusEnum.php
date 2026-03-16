<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Chờ thanh toán',
            self::CONFIRMED => 'Đã xác nhận',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
