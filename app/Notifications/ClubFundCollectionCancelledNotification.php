<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;

class ClubFundCollectionCancelledNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubFundCollection $collection
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $collectionTitle = $this->collection->title ?: $this->collection->description ?: 'Đợt thu quỹ';
        $message = "Đợt thu {$collectionTitle} tại CLB {$this->club->name} đã bị hủy";

        return self::payload('Đợt thu đã bị hủy', $message, [
            'club_id' => $this->club->id,
            'club_fund_collection_id' => $this->collection->id,
        ]);
    }
}
