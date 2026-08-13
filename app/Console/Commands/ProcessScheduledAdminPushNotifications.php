<?php

namespace App\Console\Commands;

use App\Enums\AdminPushNotification\CampaignStatus;
use App\Jobs\SendAdminPushNotificationCampaignJob;
use App\Models\AdminPushNotificationCampaign;
use Illuminate\Console\Command;

class ProcessScheduledAdminPushNotifications extends Command
{
    protected $signature = 'admin-push-notifications:process-scheduled';
    protected $description = 'Dispatch jobs for scheduled admin push notification campaigns that are due';

    public function handle(): int
    {
        $dispatched = 0;

        AdminPushNotificationCampaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($campaigns) use (&$dispatched) {
                foreach ($campaigns as $campaign) {
                    SendAdminPushNotificationCampaignJob::dispatch($campaign->id);
                    $dispatched++;
                }
            });

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} scheduled push notification campaigns.");
        }

        return self::SUCCESS;
    }
}