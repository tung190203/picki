<?php

namespace App\Notifications;

use App\Models\Badge;

class BadgeGrantedNotification extends ClubNotificationBase
{
    public function __construct(
        public string $code,
        public ?int $grantedBy = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $badge = Badge::where('code', $this->code)->first();
        $label = $badge ? $badge->name : $this->code;
        $message = "Chúc mừng! Bạn đã được cấp huy hiệu {$label}.";

        return self::payload('Bạn nhận được huy hiệu mới!', $message, [
            'badge_type' => $this->code,
            'badge_label' => $label,
            'granted_by' => $this->grantedBy,
            'action' => 'open_badges',
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $badge = Badge::where('code', $this->code)->first();
        $label = $badge ? $badge->name : $this->code;

        return [
            'title' => 'Bạn nhận được huy hiệu mới!',
            'body' => "Chúc mừng! Bạn đã được cấp huy hiệu {$label}.",
            'data' => [
                'type' => 'BADGE_GRANTED',
                'badge_type' => $this->code,
                'badge_label' => $label,
                'granted_by' => $this->grantedBy ? (string) $this->grantedBy : null,
                'action' => 'open_badges',
            ],
        ];
    }
}
