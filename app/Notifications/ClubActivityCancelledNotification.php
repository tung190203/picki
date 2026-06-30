<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;

class ClubActivityCancelledNotification extends ClubNotificationBase
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
        $message = "Sự kiện {$this->activity->title} tại CLB {$this->club->name} đã bị hủy";

        return self::payload('Sự kiện đã bị hủy', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
        ]);
    }
}
