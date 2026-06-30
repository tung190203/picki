<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    protected string $fcmTitle = 'Thông báo mới';
    protected string $fcmBody = 'Bạn có một thông báo từ Picki';

    public function toFcm(object $notifiable): array
    {
        $db = $this->toDatabase($notifiable);

        $title = $db['title'] ?? $this->fcmTitle;
        $body = $db['message'] ?? $this->fcmBody;

        unset($db['title'], $db['message']);

        $data = $db;
        $data['type'] = class_basename(static::class);

        return [
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ];
    }
}
