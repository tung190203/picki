<?php

namespace App\Enums;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';
    case PAY_PENDING = 'pay_pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Chờ thanh toán',
            self::PAY_PENDING => 'Chờ xác nhận',
            self::CONFIRMED => 'Đã xác nhận',
            self::CANCELLED => 'Đã hủy',
        };
    }
}
