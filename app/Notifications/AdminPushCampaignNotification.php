<?php

namespace App\Notifications;

use App\Models\AdminPushNotificationCampaign;

class AdminPushCampaignNotification extends BaseNotification
{
    public function __construct(
        public AdminPushNotificationCampaign $campaign
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        return self::payload($this->campaign->title, $this->campaign->content, [
            'type' => 'ADMIN_PUSH_NOTIFICATION',
            'campaign_id' => $this->campaign->id,
            'action_type' => $this->campaign->action_type?->value,
            'action_id' => $this->campaign->action_id,
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $data = [
            'type' => 'ADMIN_PUSH_NOTIFICATION',
            'campaign_id' => (string) $this->campaign->id,
        ];

        if ($this->campaign->action_type && $this->campaign->action_id) {
            $data['action_type'] = $this->campaign->action_type->value;
            $data['action_id'] = (string) $this->campaign->action_id;
        }

        return [
            'title' => $this->campaign->title,
            'body' => $this->campaign->content,
            'data' => $data,
        ];
    }

    protected static function payload(string $title, string $message, array $redirect = []): array
    {
        return array_merge(
            [
                'title' => $title,
                'message' => $message,
            ],
            $redirect
        );
    }
}
