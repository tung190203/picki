<?php

namespace App\Notifications;

use App\Models\Club\Club;

class ClubJoinRequestRejectedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ?string $rejectionReason = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Yêu cầu tham gia CLB {$this->club->name} đã bị từ chối";
        if ($this->rejectionReason) {
            $message .= ": {$this->rejectionReason}";
        }

        return self::payload('Yêu cầu tham gia CLB đã bị từ chối', $message, [
            'club_id' => $this->club->id,
            'rejection_reason' => $this->rejectionReason,
        ]);
    }
}
