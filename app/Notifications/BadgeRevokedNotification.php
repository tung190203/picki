<?php

namespace App\Notifications;

use App\Models\Badge;

class BadgeRevokedNotification extends ClubNotificationBase
{
    public function __construct(
        public string $code,
        public ?int $revokedBy = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $badge = Badge::where('code', $this->code)->first();
        $label = $badge ? $badge->name : $this->code;
        $message = "Huy hiệu {$label} của bạn đã bị thu hồi.";

        return self::payload('Huy hiệu đã bị thu hồi', $message, [
            'badge_type' => $this->code,
            'badge_label' => $label,
            'revoked_by' => $this->revokedBy,
            'action' => 'open_badges',
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $badge = Badge::where('code', $this->code)->first();
        $label = $badge ? $badge->name : $this->code;

        return [
            'title' => 'Huy hiệu đã bị thu hồi',
            'body' => "Huy hiệu {$label} của bạn đã bị thu hồi.",
            'data' => [
                'type' => 'BADGE_REVOKED',
                'badge_type' => $this->code,
                'badge_label' => $label,
                'revoked_by' => $this->revokedBy ? (string) $this->revokedBy : null,
                'action' => 'open_badges',
            ],
        ];
    }
}
