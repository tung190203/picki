<?php

namespace App\Console\Commands;

use App\Enums\ClubActivityStatus;
use App\Models\Club\ClubActivity;
use App\Services\Club\ClubActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AutoCompleteActivitiesCommand extends Command
{
    protected $signature = 'activities:auto-complete';
    protected $description = 'Tự động cập nhật status: scheduled->ongoing (đang diễn ra), scheduled/ongoing->completed (đã qua end_time). Với recurring: tạo occurrence tiếp theo.';

    public function __construct(
        private ClubActivityService $activityService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $completed = 0;
        $ongoing = 0;
        $affectedClubIds = [];

        $toOngoing = ClubActivity::where('status', ClubActivityStatus::Scheduled)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->get();

        foreach ($toOngoing as $activity) {
            $activity->update(['status' => ClubActivityStatus::Ongoing]);
            $ongoing++;
            $affectedClubIds[$activity->club_id] = true;
            $this->line("  → Ongoing: #{$activity->id} {$activity->title}");
        }

        $toComplete = ClubActivity::whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
            ->where('end_time', '<', $now)
            ->get();

        foreach ($toComplete as $activity) {
            $activity->update(['status' => ClubActivityStatus::Completed]);
            $completed++;
            $affectedClubIds[$activity->club_id] = true;
            $this->line("  ✓ Completed: #{$activity->id} {$activity->title}");

            $this->activityService->notifyCreatorToCollectFees($activity);

            if ($activity->isRecurring()) {
                $activity->refresh();
                $this->activityService->ensureNextOccurrenceForCompleted($activity);
            }
        }

        // Bump cache version cho tất cả club bị ảnh hưởng
        foreach (array_keys($affectedClubIds) as $clubId) {
            Cache::increment('club_content_version:' . $clubId);
        }

        $this->info("Đã cập nhật: {$ongoing} ongoing, {$completed} completed.");
        return 0;
    }
}
