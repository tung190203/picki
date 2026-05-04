<?php

namespace App\Notifications;

use App\Models\Tournament;

class TournamentPaymentReminderNotification extends ClubNotificationBase
{
    public function __construct(
        public Tournament $tournament,
        public ?float $amountDue = null
    ) {
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Bạn được nhắc nhở đóng phí tham gia giải đấu \"{$this->tournament->name}\".";
        if ($this->amountDue) {
            $message .= " Số tiền: " . number_format($this->amountDue) . " VNĐ.";
        }

        return self::payload('Nhắc nhở đóng phí giải đấu', $message, [
            'tournament_id' => $this->tournament->id,
            'amount_due' => $this->amountDue,
        ]);
    }
}
