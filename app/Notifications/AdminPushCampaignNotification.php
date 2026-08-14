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
        $payload = [
            'type' => 'ADMIN_PUSH_NOTIFICATION',
            'campaign_id' => $this->campaign->id,
            'action_type' => $this->campaign->action_type?->value,
            'action_id' => $this->campaign->action_id,
        ];

        if ($this->campaign->action_type && $this->campaign->action_id) {
            $payload['action_url'] = match ($this->campaign->action_type->value) {
                'TOURNAMENT' => "tournament-detail/{$this->campaign->action_id}",
                'MINI_TOURNAMENT' => "mini-tournament-detail/{$this->campaign->action_id}",
                'CLUB' => "club-detail/{$this->campaign->action_id}",
                default => null,
            };
        }

        if ($this->campaign->image_url) {
            $payload['image_url'] = $this->campaign->image_url;
        }

        return self::payload($this->campaign->title, $this->campaign->content, $payload);
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
            $data['action_url'] = match ($this->campaign->action_type->value) {
                'TOURNAMENT' => "tournament-detail/{$this->campaign->action_id}",
                'MINI_TOURNAMENT' => "mini-tournament-detail/{$this->campaign->action_id}",
                'CLUB' => "club-detail/{$this->campaign->action_id}",
                default => null,
            };
        }

        $result = [
            'title' => $this->campaign->title,
            'body' => $this->campaign->content,
            'data' => $data,
        ];

        if ($this->campaign->image_url) {
            $result['image'] = $this->campaign->image_url;
        }

        return $result;
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
