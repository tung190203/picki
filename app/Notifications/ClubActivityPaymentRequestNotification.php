<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubWalletTransaction;

class ClubActivityPaymentRequestNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubActivity $activity,
        public ClubWalletTransaction $transaction
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $amount = number_format($this->transaction->amount);
        $message = "Bạn cần thanh toán {$amount} VND cho sự kiện {$this->activity->title} tại CLB {$this->club->name}";

        return self::payload('Yêu cầu thanh toán sự kiện', $message, [
            'club_id' => $this->club->id,
            'club_activity_id' => $this->activity->id,
            'club_wallet_transaction_id' => $this->transaction->id,
        ]);
    }
}
