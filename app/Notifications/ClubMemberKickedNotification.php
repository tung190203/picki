<?php

namespace App\Notifications;

use App\Models\Club\Club;

class ClubMemberKickedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Bạn đã bị buộc rời khỏi CLB {$this->club->name}";

        return self::payload('Bạn đã bị buộc rời khỏi CLB', $message, [
            'club_id' => $this->club->id,
        ]);
    }
}
