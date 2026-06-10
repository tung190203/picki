<?php

namespace App\Enums;

enum SessionStatus: string
{
    case PendingGroup = 'pending_group';
    case Ready = 'ready';
    case Ongoing = 'ongoing';
    case Finished = 'finished';

    public function label(): string
    {
        return match($this) {
            self::PendingGroup => 'Chờ phân nhóm',
            self::Ready => 'Sẵn sàng',
            self::Ongoing => 'Đang đấu',
            self::Finished => 'Đã kết thúc',
        };
    }
}
