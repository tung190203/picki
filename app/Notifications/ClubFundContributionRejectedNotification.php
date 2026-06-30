<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;

class ClubFundContributionRejectedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubFundCollection $collection,
        public ClubFundContribution $contribution,
        public ?string $rejectionReason = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $collectionTitle = $this->collection->title ?: $this->collection->description ?: 'Đợt thu quỹ';
        $message = "Yêu cầu thanh toán của bạn cho khoản thu {$collectionTitle} đã bị từ chối";
        if ($this->rejectionReason) {
            $message .= ": {$this->rejectionReason}";
        }

        return self::payload('Thanh toán đã bị từ chối', $message, [
            'club_id' => $this->club->id,
            'club_fund_collection_id' => $this->collection->id,
            'club_fund_contribution_id' => $this->contribution->id,
            'rejection_reason' => $this->rejectionReason,
        ]);
    }
}
