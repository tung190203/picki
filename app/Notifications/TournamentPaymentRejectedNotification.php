<?php

namespace App\Notifications;

use App\Models\TournamentParticipantPayment;

class TournamentPaymentRejectedNotification extends ClubNotificationBase
{
    public function __construct(
        public TournamentParticipantPayment $payment,
        public string $reason
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $tournament = $this->payment->tournament;

        return self::payload(
            'Thanh toán bị từ chối',
            "Thanh toán phí giải đấu \"{$tournament->name}\" bị từ chối. Lý do: {$this->reason}",
            [
                'tournament_id' => $tournament->id,
                'payment_id' => $this->payment->id,
                'reason' => $this->reason,
            ]
        );
    }
}
