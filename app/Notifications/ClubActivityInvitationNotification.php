<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;

class ClubActivityInvitationNotification extends ClubNotificationBase
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
        $message = "Bạn được mời tham gia sự kiện {$this->activity->title} tại CLB {$this->club->name}";

        return self::payload('Lời mời tham gia sự kiện', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
        ]);
    }
}
