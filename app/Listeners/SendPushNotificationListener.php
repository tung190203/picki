<?php

namespace App\Listeners;

use App\Jobs\SendPushJob;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;

class SendPushNotificationListener
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notifiable instanceof User) {
            return;
        }

        $payload = $this->resolvePayload($event);

        if ($payload === null) {
            return;
        }

        SendPushJob::dispatch(
            $event->notifiable->id,
            $payload['title'],
            $payload['body'],
            $payload['data']
        );
    }

    protected function resolvePayload(NotificationSent $event): ?array
    {
        $notification = $event->notification;

        if (method_exists($notification, 'toFcm')) {
            $fcm = $notification->toFcm($event->notifiable);
            if (is_array($fcm) && isset($fcm['title'], $fcm['body'])) {
                return [
                    'title' => $fcm['title'],
                    'body' => $fcm['body'],
                    'data' => $fcm['data'] ?? [],
                ];
            }
        }

        if (method_exists($notification, 'toDatabase')) {
            try {
                $db = $notification->toDatabase($event->notifiable);
                if (! is_array($db)) {
                    return null;
                }

                $title = $db['title'] ?? 'Thông báo mới';
                $body = $db['message'] ?? 'Bạn có một thông báo từ Picki';

                unset($db['title'], $db['message']);

                return [
                    'title' => $title,
                    'body' => $body,
                    'data' => array_merge($db, ['type' => class_basename($notification)]),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
