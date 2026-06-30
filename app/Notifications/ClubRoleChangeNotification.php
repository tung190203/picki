<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;

class ClubRoleChangeNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubMember $member,
        public string $newRoleLabel,
        public int $updaterId
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Bạn được bổ nhiệm làm {$this->newRoleLabel} trong CLB {$this->club->name}";

        return self::payload('Bạn được bổ nhiệm làm ' . $this->newRoleLabel, $message, [
            'club_id' => $this->club->id,
            'club_member_id' => $this->member->id,
            'new_role' => $this->member->role->value,
            'updated_by' => $this->updaterId,
        ]);
    }
}
