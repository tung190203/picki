<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\User;

class ClubActivityJoinRequestNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubActivity $activity,
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
        $message = "{$applicantName} muốn tham gia sự kiện {$this->activity->title} tại CLB {$this->club->name}";

        return self::payload('Yêu cầu tham gia sự kiện', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
            'applicant_id' => $this->applicant->id,
            'applicant_name' => $applicantName,
        ]);
    }
}
