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

        // Chỉ xử lý cho channel 'database' để tránh duplicate FCM
        // (channel 'broadcast' cũng fire NotificationSent event nhưng nội dung giống nhau)
        if ($event->channel !== 'database') {
            return;
        }

        // Skip push notifications in non-production environments (local/dev/staging).
        // Database notifications are still saved by Laravel before this listener fires.
        if (in_array(config('app.env'), ['local', 'development', 'dev', 'staging'], true)) {
            return;
        }

        $payload = $this->resolvePayload($event);

        if ($payload === null) {
            return;
        }

        // Extract campaign_id from data if present (for AdminPushCampaignNotification)
        $campaignId = $payload['data']['campaign_id'] ?? null;
        if ($campaignId !== null) {
            $campaignId = (int) $campaignId;
        }

        SendPushJob::dispatch(
            $event->notifiable->id,
            $payload['title'],
            $payload['body'],
            $payload['data'],
            $campaignId
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
