<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;

class ClubFundCollectionCreatedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubFundCollection $collection,
        public ?float $amountDue = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $collectionTitle = $this->collection->title ?: $this->collection->description ?: 'Đợt thu quỹ';
        $message = "Bạn có khoản thu mới cần đóng: {$collectionTitle} tại CLB {$this->club->name}";
        if ($this->amountDue > 0) {
            $message .= ' - Số tiền: ' . number_format($this->amountDue, 0, ',', '.') . ' VND';
        }

        return self::payload('Khoản thu mới cần đóng', $message, [
            'club_id' => $this->club->id,
            'club_fund_collection_id' => $this->collection->id,
            'collection_title' => $collectionTitle,
            'amount_due' => $this->amountDue,
        ]);
    }
}
