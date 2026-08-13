<?php

namespace App\Notifications;

use App\Enums\BadgeType;

class BadgeGrantedNotification extends ClubNotificationBase
{
    public function __construct(
        public BadgeType $badgeType,
        public ?int $grantedBy = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Chúc mừng! Bạn đã được cấp huy hiệu {$this->badgeType->label()}.";

        return self::payload('Bạn nhận được huy hiệu mới!', $message, [
            'badge_type' => $this->badgeType->value,
            'badge_label' => $this->badgeType->label(),
            'granted_by' => $this->grantedBy,
            'action' => 'open_badges',
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Bạn nhận được huy hiệu mới!',
            'body' => "Chúc mừng! Bạn đã được cấp huy hiệu {$this->badgeType->label()}.",
            'data' => [
                'type' => 'BADGE_GRANTED',
                'badge_type' => $this->badgeType->value,
                'badge_label' => $this->badgeType->label(),
                'granted_by' => $this->grantedBy ? (string) $this->grantedBy : null,
                'action' => 'open_badges',
            ],
        ];
    }
}
