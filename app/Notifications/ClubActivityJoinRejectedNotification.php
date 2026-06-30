<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;

class ClubActivityJoinRejectedNotification extends ClubNotificationBase
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
        $message = "Yêu cầu tham gia sự kiện {$this->activity->title} đã bị từ chối";

        return self::payload('Yêu cầu tham gia sự kiện đã bị từ chối', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
        ]);
    }
}
