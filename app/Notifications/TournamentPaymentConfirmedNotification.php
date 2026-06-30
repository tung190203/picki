<?php

namespace App\Notifications;

use App\Models\TournamentParticipantPayment;

class TournamentPaymentConfirmedNotification extends ClubNotificationBase
{
    public function __construct(
        public TournamentParticipantPayment $payment
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
            'Xác nhận thanh toán thành công',
            "Thanh toán phí giải đấu \"{$tournament->name}\" đã được xác nhận.",
            [
                'tournament_id' => $tournament->id,
                'payment_id' => $this->payment->id,
            ]
        );
    }
}
