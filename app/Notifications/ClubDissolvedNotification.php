<?php

namespace App\Notifications;

use App\Models\Club\Club;

class ClubDissolvedNotification extends ClubNotificationBase
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
        $message = "CLB {$this->club->name} đã bị giải tán";

        return self::payload('CLB đã giải tán', $message, [
            'club_id' => $this->club->id,
        ]);
    }
}
