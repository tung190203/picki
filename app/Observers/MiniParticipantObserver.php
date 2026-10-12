<?php

namespace App\Observers;

use App\Models\MiniParticipant;
use App\Services\Club\ClubGuestEventService;
use Illuminate\Support\Facades\Log;

/**
 * Real-time tracking: thêm user vào club_guests NGAY khi đăng ký (confirm).
 * Logic: user đã được add thì khi cronjob chạy sẽ idempotent (tăng play_count).
 * Nếu user hủy trước start_time thì play_count đã tăng sẽ KHÔNG giảm lại - chấp nhận đánh đổi
 * để tránh race condition phức tạp. Cronjob vẫn là nguồn chính cập nhật cuối cùng.
 */
class MiniParticipantObserver
{
    public function __construct(protected ClubGuestEventService $guestEventService) {}

    public function created(MiniParticipant $participant): void
    {
        // Không làm gì - đợi cronjob trigger khi đến start_time
        // (giảm thiểu partial state nếu user hủy trước start_time)
    }
}
