<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;

class ClubInvitationNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubMember $member,
        public string $inviterName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Bạn được mời tham gia CLB {$this->club->name} bởi {$this->inviterName}";

        return self::payload('Lời mời tham gia CLB', $message, [
            'club_id' => $this->club->id,
            'club_member_id' => $this->member->id,
            'invited_by' => $this->member->invited_by,
            'inviter_name' => $this->inviterName,
            'role' => $this->member->role->value,
        ]);
    }
}
