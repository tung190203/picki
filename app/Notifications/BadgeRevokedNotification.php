<?php

namespace App\Notifications;

use App\Enums\BadgeType;

class BadgeRevokedNotification extends ClubNotificationBase
{
    public function __construct(
        public BadgeType $badgeType,
        public ?int $revokedBy = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Huy hiệu {$this->badgeType->label()} của bạn đã bị thu hồi.";

        return self::payload('Huy hiệu đã bị thu hồi', $message, [
            'badge_type' => $this->badgeType->value,
            'badge_label' => $this->badgeType->label(),
            'revoked_by' => $this->revokedBy,
            'action' => 'open_badges',
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Huy hiệu đã bị thu hồi',
            'body' => "Huy hiệu {$this->badgeType->label()} của bạn đã bị thu hồi.",
            'data' => [
                'type' => 'BADGE_REVOKED',
                'badge_type' => $this->badgeType->value,
                'badge_label' => $this->badgeType->label(),
                'revoked_by' => $this->revokedBy ? (string) $this->revokedBy : null,
                'action' => 'open_badges',
            ],
        ];
    }
}
