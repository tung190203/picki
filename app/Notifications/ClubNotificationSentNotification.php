<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubNotification;

class ClubNotificationSentNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubNotification $clubNotification
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = $this->clubNotification->title ?: 'Thông báo từ CLB';
        $message = (string) ($this->clubNotification->content ?: $this->club->name);

        return self::payload($title, $message, [
            'club_id' => $this->club->id,
            'club_notification_id' => $this->clubNotification->id,
            'attachment_url' => $this->clubNotification->attachment_url,
            'priority' => $this->clubNotification->priority?->value,
            'metadata' => $this->clubNotification->metadata,
            'created_by' => $this->clubNotification->created_by,
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $title = $this->clubNotification->title ?: 'Thông báo từ CLB';
        $body = (string) ($this->clubNotification->content ?: $this->club->name);

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'CLUB_NOTIFICATION',
                'club_id' => (string) $this->club->id,
                'club_notification_id' => (string) $this->clubNotification->id,
            ],
        ];
    }
}
