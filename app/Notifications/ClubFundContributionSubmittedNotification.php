<?php

namespace App\Notifications;

use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\User;

class ClubFundContributionSubmittedNotification extends ClubNotificationBase
{
    public function __construct(
        public Club $club,
        public ClubFundCollection $collection,
        public ClubFundContribution $contribution,
        public User $submitter
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $submitterName = $this->submitter->full_name ?: $this->submitter->email;
        $collectionTitle = $this->collection->title ?: $this->collection->description ?: 'Đợt thu quỹ';
        $message = "{$submitterName} đã nộp thanh toán cho khoản thu {$collectionTitle} tại CLB {$this->club->name}";

        return self::payload('Yêu cầu thanh toán mới', $message, [
            'club_id' => $this->club->id,
            'club_fund_collection_id' => $this->collection->id,
            'club_fund_contribution_id' => $this->contribution->id,
            'submitter_id' => $this->submitter->id,
            'submitter_name' => $submitterName,
            'amount' => (float) $this->contribution->amount,
        ]);
    }
}
