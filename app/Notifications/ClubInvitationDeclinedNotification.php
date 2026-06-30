<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\User;

class ClubInvitationDeclinedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public User $declinedUser
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $userName = $this->declinedUser->full_name ?: $this->declinedUser->email ?: 'Thành viên';
        $message = "{$userName} đã từ chối lời mời tham gia CLB {$this->club->name}";

        return self::payload('Từ chối lời mời tham gia CLB', $message, [
            'club_id' => (string) $this->club->id,
            'declined_user_id' => (string) $this->declinedUser->id,
        ]);
    }
}
