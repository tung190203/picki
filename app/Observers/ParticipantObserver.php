<?php

namespace App\Observers;

use App\Models\Participant;
use App\Services\Club\ClubGuestEventService;
use Illuminate\Support\Facades\Log;

/**
 * Real-time tracking: thêm user vào club_guests NGAY khi đăng ký (confirm).
 * Cronjob là nguồn chính cập nhật cuối cùng.
 */
class ParticipantObserver
{
    public function __construct(protected ClubGuestEventService $guestEventService) {}

    public function created(Participant $participant): void
    {
        // Không làm gì - đợi cronjob trigger khi đến start_time
    }
}
