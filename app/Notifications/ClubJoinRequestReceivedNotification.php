<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\User;

class ClubJoinRequestReceivedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public User $applicant
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $applicantName = $this->applicant->full_name ?: $this->applicant->email;
        $message = "Có yêu cầu tham gia mới từ {$applicantName} tại CLB {$this->club->name}";

        return self::payload('Yêu cầu tham gia CLB mới', $message, [
            'club_id' => $this->club->id,
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $applicantName,
        ]);
    }
}
