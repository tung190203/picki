<?php

namespace App\Console\Commands;

use App\Enums\ClubActivityStatus;
use App\Models\Club\ClubActivity;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateRecurringActivities extends Command
{
    protected $signature = 'activities:generate-recurring {--days-ahead=30 : Number of days to look ahead}';

    protected $description = 'Tự động tạo activities mới cho các hoạt động lặp lại đã hoàn thành';

    public function handle()
    {
        $daysAhead = (int) $this->option('days-ahead');
        $lookAheadDate = Carbon::now()->addDays($daysAhead);

        $this->info("Đang tìm các recurring activities cần tạo cho {$daysAhead} ngày tới...");

        $recurringActivities = ClubActivity::whereNotNull('recurring_schedule')
            ->where('status', ClubActivityStatus::Completed)
            ->get();

        if ($recurringActivities->isEmpty()) {
            $this->info('Không có recurring activity nào đã completed');
            return 0;
        }

        $totalGenerated = 0;

        foreach ($recurringActivities as $activity) {
            if (!$activity->isRecurring()) {
                continue;
            }

            $generated = $this->generateMissingOccurrences($activity, $lookAheadDate);
            $totalGenerated += $generated;
        }

        if ($totalGenerated > 0) {
            $this->info("✓ Đã tạo {$totalGenerated} hoạt động mới từ recurring schedule");
        } else {
            $this->info('Không có hoạt động nào cần tạo');
        }

        return 0;
    }

    private function generateMissingOccurrences(ClubActivity $activity, Carbon $lookAheadDate): int
    {
        $count = 0;
        $maxIterations = 100;
        $iteration = 0;

        $lastOccurrence = ClubActivity::where('club_id', $activity->club_id)
            ->where('title', $activity->title)
            ->where('recurring_schedule', $activity->attributes['recurring_schedule'] ?? null)
            ->whereIn('status', [
                ClubActivityStatus::Scheduled,
                ClubActivityStatus::Ongoing,
                ClubActivityStatus::Completed
            ])
            ->orderBy('start_time', 'desc')
            ->first();

        $fromDate = $lastOccurrence
            ? ($lastOccurrence->end_time ?? $lastOccurrence->start_time)
            : ($activity->end_time ?? $activity->start_time);

        while ($iteration < $maxIterations) {
            $iteration++;

            $nextStartTime = $activity->calculateNextOccurrence($fromDate);

            if (!$nextStartTime || $nextStartTime->gt($lookAheadDate)) {
                break;
            }

            $existing = $this->hasNearDuplicate(
                $activity,
                $nextStartTime
            );

            if (!$existing) {
                $newActivity = $this->createNextOccurrence($activity, $nextStartTime);

                if ($newActivity) {
                    $count++;
                    $this->line("  ✓ Tạo: {$newActivity->title} - {$nextStartTime->format('d/m/Y H:i')}");
                }
            }

            $fromDate = $nextStartTime->copy()->addMinute();
        }

        return $count;
    }

    /**
     * Check duplicate với window ±2 giờ thay vì exact match.
     * Tránh trường hợp user sửa kèo trong series tạo ra occurrence trùng.
     */
    private function hasNearDuplicate(ClubActivity $activity, Carbon $nextStartTime): bool
    {
        $windowStart = $nextStartTime->copy()->subHours(2);
        $windowEnd = $nextStartTime->copy()->addHours(2);

        $schedule = $activity->attributes['recurring_schedule'] ?? null;

        return ClubActivity::where('club_id', $activity->club_id)
            ->where('title', $activity->title)
            ->where('recurring_schedule', $schedule)
            ->whereBetween('start_time', [$windowStart, $windowEnd])
            ->exists();
    }

    private function createNextOccurrence(ClubActivity $activity, Carbon $nextStartTime): ?ClubActivity
    {
        $duration = $activity->duration ?? ($activity->end_time ? $activity->start_time->diffInMinutes($activity->end_time) : null);
        $nextEndTime = $duration ? $nextStartTime->copy()->addMinutes($duration) : null;

        $nextCancellationDeadline = null;
        if ($activity->cancellation_deadline && $activity->start_time) {
            $minutesBeforeStart = $activity->cancellation_deadline->diffInMinutes($activity->start_time, false);
            if ($minutesBeforeStart > 0) {
                $nextCancellationDeadline = $nextStartTime->copy()->subMinutes($minutesBeforeStart);
            }
        }

        $newActivity = $activity->replicate([
            'status',
            'cancellation_reason',
            'cancelled_by',
            'check_in_token',
        ]);

        $newActivity->start_time = $nextStartTime;
        $newActivity->end_time = $nextEndTime;
        $newActivity->cancellation_deadline = $nextCancellationDeadline;
        $newActivity->status = ClubActivityStatus::Scheduled;
        $newActivity->recurrence_series_id = $activity->recurrence_series_id;
        $newActivity->recurrence_series_cancelled_at = null;
        $newActivity->save();

        \App\Models\Club\ClubActivityParticipant::create([
            'club_activity_id' => $newActivity->id,
            'user_id' => $activity->created_by,
            'status' => \App\Enums\ClubActivityParticipantStatus::Accepted,
        ]);

        $checkInToken = \Illuminate\Support\Str::random(48);
        $newActivity->update(['check_in_token' => $checkInToken]);

        return $newActivity;
    }
}
