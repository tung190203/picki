<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;

class ClubActivityCompletedFeeReminderNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubActivity $activity
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Hoạt động {$this->activity->title} đã hoàn thành. Hãy vào chốt bill thu tiền sự kiện tại CLB {$this->club->name}";

        return self::payload('Nhắc thu tiền sự kiện', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
        ]);
    }
}
