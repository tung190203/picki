<?php

namespace App\Notifications;

use App\Models\SystemNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SystemNotificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SystemNotification $systemNotification
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'SYSTEM_NOTIFICATION',
            'system_notification_id' => $this->systemNotification->id,
            'title' => $this->systemNotification->title,
            'message' => $this->systemNotification->body,
            'data' => $this->systemNotification->data ?? [],
        ];
    }
}
