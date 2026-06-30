<?php

namespace App\Notifications;

use App\Models\Club\Club;

class ClubRenamedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public string $oldName,
        public string $newName
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "CLB {$this->oldName} đã được quản trị viên đổi tên thành {$this->newName}";

        return self::payload('CLB đã đổi tên', $message, [
            'club_id' => $this->club->id,
            'old_name' => $this->oldName,
            'new_name' => $this->newName,
        ]);
    }
}
