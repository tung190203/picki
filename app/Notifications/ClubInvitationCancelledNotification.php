<?php

namespace App\Notifications;

use App\Models\Club\Club;

class ClubInvitationCancelledNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public string $inviterName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Lời mời tham gia CLB {$this->club->name} đã bị hủy bởi {$this->inviterName}";

        return self::payload('Lời mời tham gia CLB đã bị hủy', $message, [
            'club_id' => $this->club->id,
            'inviter_name' => $this->inviterName,
        ]);
    }
}
