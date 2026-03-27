<?php

namespace App\Console\Commands;

use App\Enums\ClubActivityStatus;
use App\Models\Club\ClubActivity;
use App\Services\Club\ClubActivityService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoCompleteActivities extends Command
{
    protected $signature = 'activities:auto-complete';

    protected $description = 'Đồng bộ status activity theo thời gian (scheduled/ongoing/completed)';

    public function __construct(
        protected ClubActivityService $activityService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $activities = ClubActivity::where('status', '!=', ClubActivityStatus::Cancelled)
            ->get();

        $now = Carbon::now();
        $updatedCompleted = 0;
        $updatedOngoing = 0;
        $updatedScheduled = 0;
        $affectedClubIds = [];

        foreach ($activities as $activity) {
            $targetStatus = $this->computeStatus($activity, $now);

            if ($activity->status !== $targetStatus) {
                $activity->update(['status' => $targetStatus]);
                $affectedClubIds[$activity->club_id] = true;

                $activityInfo = "#{$activity->id} '{$activity->title}'";
                match ($targetStatus) {
                    ClubActivityStatus::Completed => $this->info("✓ Activity {$activityInfo} → completed"),
                    ClubActivityStatus::Ongoing => $this->info("✓ Activity {$activityInfo} → ongoing"),
                    ClubActivityStatus::Scheduled => $this->info("✓ Activity {$activityInfo} → scheduled"),
                    default => null,
                };

                match ($targetStatus) {
                    ClubActivityStatus::Completed => $updatedCompleted++,
                    ClubActivityStatus::Ongoing => $updatedOngoing++,
                    ClubActivityStatus::Scheduled => $updatedScheduled++,
                    default => null,
                };

                // Recurring: tạo occurrence tiếp theo khi activity hoàn thành (chỉ khi complete, không khi cancel)
                if ($targetStatus === ClubActivityStatus::Completed && $activity->isRecurring()) {
                    try {
                        $this->activityService->ensureNextOccurrenceForCompleted($activity);
                    } catch (\Throwable $e) {
                        $this->warn("Không thể tạo occurrence tiếp theo cho #{$activity->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        $total = $updatedCompleted + $updatedOngoing + $updatedScheduled;
        if ($total > 0) {
            foreach (array_keys($affectedClubIds) as $clubId) {
                Cache::increment('club_content_version:' . $clubId);
            }
            $this->info("Đã đồng bộ {$total} hoạt động (completed: {$updatedCompleted}, ongoing: {$updatedOngoing}, scheduled: {$updatedScheduled})");
        } else {
            $this->info('Không có hoạt động nào cần cập nhật');
        }

        return 0;
    }

    private function computeStatus(ClubActivity $activity, Carbon $now): ClubActivityStatus
    {
        if ($activity->end_time && $activity->end_time->lte($now)) {
            return ClubActivityStatus::Completed;
        }
        if ($activity->start_time && $activity->end_time
            && $now->gte($activity->start_time) && $now->lt($activity->end_time)) {
            return ClubActivityStatus::Ongoing;
        }

        return ClubActivityStatus::Scheduled;
    }
}
