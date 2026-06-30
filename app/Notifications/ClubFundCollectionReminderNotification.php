<?php

namespace App\Notifications;

use App\Models\Club\ClubFundCollection;

class ClubFundCollectionReminderNotification extends ClubNotificationBase
{
    public function __construct(
        public ClubFundCollection $collection,
        public string $collectionTitle,
        public string $clubName,
        public ?float $amountDue = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = "Bạn được nhắc nhở đóng khoản thu {$this->collectionTitle} ở CLB {$this->clubName}";

        return self::payload('Nhắc nhở đóng khoản thu', $message, [
            'club_id' => $this->collection->club_id,
            'club_fund_collection_id' => $this->collection->id,
            'collection_title' => $this->collectionTitle,
            'amount_due' => $this->amountDue,
        ]);
    }
}
