<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;

class ClubActivityJoinApprovedNotification extends ClubNotificationBase
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
        $message = "Yêu cầu tham gia sự kiện {$this->activity->title} đã được duyệt";

        return self::payload('Yêu cầu tham gia sự kiện đã được duyệt', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
        ]);
    }
}
