<?php

namespace App\Services\Club;

use App\Enums\ClubActivityFeeSplitType;
use App\Enums\ClubActivityParticipantStatus;
use App\Enums\ClubActivityStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Enums\PaymentMethod;
use App\Models\Club\Club;
use App\Jobs\SendPushJob;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubActivityParticipant;
use App\Models\Club\ClubWalletTransaction;
use App\Models\User;
use App\Notifications\ClubActivityCancelledNotification;
use App\Notifications\ClubActivityCompletedFeeReminderNotification;
use App\Services\ImageOptimizationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubActivityService
{
    public function __construct(
        protected ImageOptimizationService $imageService
    ) {
    }

    public function getActivities(Club $club, array $filters, ?int $userId): LengthAwarePaginator
    {
        $query = $club->activities()
            ->with([
                'participants.user',
                'creator',
                'fundCollection:id,club_activity_id',
            ]);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $statuses = $filters['statuses'] ?? [];
        $hasAll = in_array('all', $statuses);
        $hasRegistered = in_array('registered', $statuses);
        $hasAvailable = in_array('available', $statuses);
        $activityStatuses = array_intersect($statuses, ['scheduled', 'ongoing', 'completed', 'cancelled']);

        $isHistoryOnly = !empty($activityStatuses)
            && empty(array_diff($activityStatuses, ['completed', 'cancelled']));

        if (!$hasAll && !empty($statuses)) {
            if (($hasRegistered || $hasAvailable) && empty($activityStatuses)) {
                $query->whereIn('status', ['scheduled', 'ongoing']);
            } elseif (!empty($activityStatuses)) {
            if ($isHistoryOnly) {
                // History tab: only query completed/cancelled within last 3 months
                $query->whereIn('status', ['completed', 'cancelled'])
                    ->where('start_time', '>=', now()->subMonths(3));
            } else {
                $query->whereIn('status', $activityStatuses);
            }
            }

            if (!empty($activityStatuses) && !in_array('completed', $activityStatuses) && !in_array('cancelled', $activityStatuses)) {
                $query->where('end_time', '>=', now());
            }

            if ($userId && ($hasRegistered || $hasAvailable)) {
                if ($hasRegistered && !$hasAvailable) {
                    $query->whereHas('participants', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
                } elseif ($hasAvailable && !$hasRegistered) {
                    $query->whereDoesntHave('participants', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
                }
            }
        } elseif (!$hasAll && empty($statuses)) {
            $query->whereIn('status', ['scheduled', 'ongoing'])
                ->where('end_time', '>=', now());
        }

        $shouldCollapseRecurring = $hasAll
            || empty($statuses)
            || in_array('scheduled', $activityStatuses)
            || in_array('ongoing', $activityStatuses)
            || (($hasRegistered || $hasAvailable) && empty($activityStatuses));

        $dateFrom = $filters['date_from'] ?? $filters['from_date'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to_date'] ?? null;
        $includeNextOccurrence = !empty($filters['include_next_occurrence_for_series_done_this_week']);

        $driver = $query->getConnection()->getDriverName();
        $periodExpr = $driver === 'mysql'
            ? "JSON_UNQUOTE(JSON_EXTRACT(club_activities.recurring_schedule, '$.period'))"
            : "json_extract(club_activities.recurring_schedule, '$.period')";

        $firstOccurrenceIdsMonthlyQuarterlyYearly = [];
        if ($shouldCollapseRecurring) {
            $periodExprSub = $driver === 'mysql'
                ? "JSON_UNQUOTE(JSON_EXTRACT(recurring_schedule, '$.period'))"
                : "json_extract(recurring_schedule, '$.period')";

            // Quarterly and yearly: show 1 (first) occurrence per series
            $firstsSeriesSub = DB::table('club_activities')
                ->select('recurrence_series_id', DB::raw('MIN(start_time) as min_start'))
                ->where('club_id', $club->id)
                ->whereNotNull('recurrence_series_id')
                ->whereNull('recurrence_series_cancelled_at')
                ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                ->whereRaw("({$periodExprSub} IN ('quarterly', 'yearly'))")
                ->groupBy('recurrence_series_id');

            $quarterlyYearlyIds = DB::table('club_activities as ca')
                ->joinSub($firstsSeriesSub, 'firsts', function ($join) {
                    $join->on('ca.recurrence_series_id', '=', 'firsts.recurrence_series_id')
                        ->on('ca.start_time', '=', 'firsts.min_start')
                        ->whereIn('ca.status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing]);
                })
                ->where('ca.club_id', $club->id)
                ->whereNull('ca.recurrence_series_cancelled_at')
                ->pluck('ca.id')
                ->all();

            // Monthly: show 2 consecutive occurrences per series
            $monthlyRows = DB::table('club_activities')
                ->where('club_id', $club->id)
                ->whereNotNull('recurrence_series_id')
                ->whereNull('recurrence_series_cancelled_at')
                ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                ->whereRaw("({$periodExprSub} = 'monthly')")
                ->orderBy('recurrence_series_id')
                ->orderBy('start_time')
                ->select(['id', 'recurrence_series_id'])
                ->get();

            $monthlyIds = $monthlyRows
                ->groupBy('recurrence_series_id')
                ->flatMap(fn ($items) => $items->take(2)->pluck('id'))
                ->values()
                ->all();

            $firstOccurrenceIdsMonthlyQuarterlyYearly = array_merge($monthlyIds, $quarterlyYearlyIds);
        }

        $nextOccurrenceIds = [];
        if ($includeNextOccurrence && !empty($dateFrom) && !empty($dateTo)) {
            $seriesCountDoneThisWeek = ClubActivity::where('club_id', $club->id)
                ->whereNotNull('recurrence_series_id')
                ->where('status', ClubActivityStatus::Completed)
                ->whereDate('start_time', '>=', $dateFrom)
                ->whereDate('start_time', '<=', $dateTo)
                ->selectRaw('recurrence_series_id, COUNT(*) as cnt')
                ->groupBy('recurrence_series_id')
                ->pluck('cnt', 'recurrence_series_id')
                ->all();
            $afterWeekEnd = Carbon::parse($dateTo)->endOfDay()->addSecond()->format('Y-m-d H:i:s');
            foreach ($seriesCountDoneThisWeek as $seriesId => $count) {
                $ids = ClubActivity::where('club_id', $club->id)
                    ->where('recurrence_series_id', $seriesId)
                    ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                    ->where('start_time', '>', $afterWeekEnd)
                    ->orderBy('start_time')
                    ->limit((int) $count)
                    ->pluck('id')
                    ->all();
                $nextOccurrenceIds = array_merge($nextOccurrenceIds, $ids);
            }
        }

        // Weekly partial-day series (< 7 days/week): also show next week's occurrences
        if ($shouldCollapseRecurring && !empty($dateFrom) && !empty($dateTo)) {
            $weeklyPeriodExprSub = $driver === 'mysql'
                ? "JSON_UNQUOTE(JSON_EXTRACT(recurring_schedule, '$.period'))"
                : "json_extract(recurring_schedule, '$.period')";

            $weeklySeriesRep = DB::table('club_activities')
                ->where('club_id', $club->id)
                ->whereNotNull('recurrence_series_id')
                ->whereNull('recurrence_series_cancelled_at')
                ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                ->whereRaw("({$weeklyPeriodExprSub} = 'weekly')")
                ->whereDate('start_time', '>=', $dateFrom)
                ->whereDate('start_time', '<=', $dateTo)
                ->orderBy('recurrence_series_id')
                ->orderBy('start_time')
                ->select(['recurrence_series_id', 'recurring_schedule'])
                ->get()
                ->unique('recurrence_series_id');

            $afterCurrentWeekEnd = Carbon::parse($dateTo)->endOfDay()->addSecond()->format('Y-m-d H:i:s');
            $nextWeekEnd = Carbon::parse($dateTo)->addWeek()->endOfDay()->format('Y-m-d H:i:s');

            foreach ($weeklySeriesRep as $row) {
                $schedule = is_string($row->recurring_schedule)
                    ? json_decode($row->recurring_schedule, true)
                    : (array) $row->recurring_schedule;
                $weekDays = $schedule['week_days'] ?? [];

                if (count($weekDays) < 7) {
                    $nextWeekIds = ClubActivity::where('club_id', $club->id)
                        ->where('recurrence_series_id', $row->recurrence_series_id)
                        ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                        ->where('start_time', '>', $afterCurrentWeekEnd)
                        ->where('start_time', '<=', $nextWeekEnd)
                        ->pluck('id')
                        ->all();
                    $nextOccurrenceIds = array_merge($nextOccurrenceIds, $nextWeekIds);
                }
            }
        }

        $hasDateRange = !empty($dateFrom) || !empty($dateTo);
        $includeMonthlyQuarterlyYearlyFirstInRange = $hasDateRange && $shouldCollapseRecurring && !empty($firstOccurrenceIdsMonthlyQuarterlyYearly);

        if ($hasDateRange || !empty($nextOccurrenceIds) || $includeMonthlyQuarterlyYearlyFirstInRange) {
            $query->where(function ($q) use ($dateFrom, $dateTo, $nextOccurrenceIds, $firstOccurrenceIdsMonthlyQuarterlyYearly, $hasDateRange, $includeMonthlyQuarterlyYearlyFirstInRange) {
                if ($hasDateRange) {
                    $q->where(function ($q2) use ($dateFrom, $dateTo) {
                        // Hoạt động không lặp lại: luôn hiển thị (không lọc theo khoảng ngày)
                        $q2->whereNull('club_activities.recurring_schedule')
                            ->orWhere(function ($q3) use ($dateFrom, $dateTo) {
                                // Hoạt động lặp lại: lọc theo khoảng ngày
                                $q3->whereNotNull('club_activities.recurring_schedule');
                                if (!empty($dateFrom)) {
                                    $q3->whereDate('start_time', '>=', $dateFrom);
                                }
                                if (!empty($dateTo)) {
                                    $q3->whereDate('start_time', '<=', $dateTo);
                                }
                            });
                    });
                }
                if (!empty($nextOccurrenceIds)) {
                    if ($hasDateRange) {
                        $q->orWhereIn('club_activities.id', $nextOccurrenceIds);
                    } else {
                        $q->whereIn('club_activities.id', $nextOccurrenceIds);
                    }
                }
                if ($includeMonthlyQuarterlyYearlyFirstInRange) {
                    $q->orWhereIn('club_activities.id', $firstOccurrenceIdsMonthlyQuarterlyYearly);
                }
            });
        }

        if ($shouldCollapseRecurring) {
            $periodExprWeekly = $driver === 'mysql'
                ? "JSON_UNQUOTE(JSON_EXTRACT(club_activities.recurring_schedule, '$.period'))"
                : "json_extract(club_activities.recurring_schedule, '$.period')";

            $query->where(function ($q) use ($periodExpr, $periodExprWeekly, $firstOccurrenceIdsMonthlyQuarterlyYearly, $dateFrom, $dateTo) {
                $q->whereNull('club_activities.recurring_schedule')
                    ->orWhereNotIn('club_activities.status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                    ->orWhere(function ($q2) use ($periodExprWeekly, $dateFrom, $dateTo) {
                        $q2->whereRaw("({$periodExprWeekly} = 'weekly')");
                        if (!empty($dateFrom)) {
                            $fourWeeksFromNow = Carbon::parse($dateFrom)->addWeeks(4)->endOfDay()->format('Y-m-d H:i:s');
                            $q2->where('club_activities.start_time', '<=', $fourWeeksFromNow);
                        }
                    })
                    ->orWhereIn('club_activities.id', $firstOccurrenceIdsMonthlyQuarterlyYearly ?: [0]);
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';

        // Custom sort: completed first, then by date (newest first)
        if ($isHistoryOnly) {
            $query->orderByRaw("CASE club_activities.status WHEN 'completed' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END")
                ->orderBy('start_time', 'desc');
        } elseif ($userId) {
            $query->selectRaw('club_activities.*, EXISTS(
                SELECT 1 FROM club_activity_participants
                WHERE club_activity_participants.club_activity_id = club_activities.id
                AND club_activity_participants.user_id = ?
                AND club_activity_participants.status IN (?, ?, ?, ?)
            ) as is_registered', [
                $userId,
                'pending',
                'invited',
                'accepted',
                'attended'
            ])
                ->orderBy('is_registered', 'desc')
                ->orderBy('start_time', $orderDirection);
        } else {
            $query->orderBy('start_time', $orderDirection);
        }

        return $query->paginate($perPage);
    }

    public function createActivity(Club $club, array $data, int $userId): ClubActivity
    {
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền tạo hoạt động');
        }

        $endTime = $data['end_time'] ?? null;
        if (!$endTime && isset($data['duration'])) {
            $startTime = Carbon::parse($data['start_time']);
            $endTime = $startTime->copy()->addMinutes($data['duration']);
        }

        $cancellationDeadline = $data['cancellation_deadline'] ?? null;
        if (!$cancellationDeadline) {
            $startTime = Carbon::parse($data['start_time']);
            if (isset($data['cancellation_deadline_minutes'])) {
                $cancellationDeadline = $startTime->copy()->subMinutes($data['cancellation_deadline_minutes']);
            } elseif (isset($data['cancellation_deadline_hours'])) {
                $cancellationDeadline = $startTime->copy()->subHours($data['cancellation_deadline_hours']);
            }
        }

        $qrCodeUrl = null;
        if (isset($data['qr_image']) && $data['qr_image'] instanceof UploadedFile) {
            $qrCodeUrl = $this->imageService->optimizeThumbnail($data['qr_image'], 'activity_qr_codes', 90);
        }

        $recurringSchedule = $data['recurring_schedule'] ?? null;
        $seriesId = $recurringSchedule ? Str::uuid()->toString() : null;

        $activity = ClubActivity::create([
            'club_id' => $club->id,
            'mini_tournament_id' => $data['mini_tournament_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'other',
            'recurring_schedule' => $recurringSchedule,
            'recurrence_series_id' => $seriesId,
            'start_time' => $data['start_time'],
            'end_time' => $endTime,
            'duration' => $data['duration'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'cancellation_deadline' => $cancellationDeadline,
            'reminder_minutes' => $data['reminder_minutes'] ?? 15,
            'fee_amount' => $data['fee_amount'] ?? 0,
            'fee_description' => $data['fee_description'] ?? null,
            'guest_fee' => $data['guest_fee'] ?? 0,
            'penalty_amount' => $data['penalty_amount'] ?? 0,
            'fee_split_type' => $data['fee_split_type'] ?? ClubActivityFeeSplitType::Fixed,
            'allow_member_invite' => isset($data['allow_member_invite']) ? (bool) $data['allow_member_invite'] : false,
            'is_public' => isset($data['is_public']) ? (bool) $data['is_public'] : true,
            'max_participants' => $data['max_participants'] ?? null,
            'status' => ClubActivityStatus::Scheduled,
            'created_by' => $userId,
            'creator_always_join' => isset($data['creator_always_join']) ? (bool) $data['creator_always_join'] : true,
        ]);

        $checkInToken = Str::random(48);
        $activity->update([
            'check_in_token' => $checkInToken,
            'qr_code_url' => $qrCodeUrl,
        ]);

        if ($activity->creator_always_join) {
            ClubActivityParticipant::firstOrCreate(
                [
                    'club_activity_id' => $activity->id,
                    'user_id' => $userId,
                ],
                [
                    'status' => ClubActivityParticipantStatus::Accepted,
                ]
            );
        }

        if ($activity->isRecurring() && $seriesId) {
            $this->createBatchOccurrencesForNewSeries($activity, $userId);
        }

        return $activity;
    }

    public function generateOccurrenceStartTimesForPeriod(ClubActivity $activity): array
    {
        $schedule = $activity->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['period'])) {
            return [];
        }

        $start = $activity->start_time ? Carbon::parse($activity->start_time) : Carbon::now();
        $timeString = $activity->start_time ? $activity->start_time->format('H:i:s') : $start->format('H:i:s');
        $period = $schedule['period'];
        $list = [];

        if ($period === 'weekly') {
            $weekDays = $schedule['week_days'] ?? [];
            if (empty($weekDays)) {
                return [];
            }
            $monthStart = $start->copy()->startOfMonth();
            $monthEnd = $start->copy()->endOfMonth();
            for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                if (in_array((int) $d->dayOfWeek, array_map('intval', $weekDays), true)) {
                    $d->setTimeFromTimeString($timeString);
                    if ($d->gte($start)) {
                        $list[] = $d->copy();
                    }
                }
            }
            return $list;
        }

        $parts = $activity->getRecurringDateParts();
        if (!$parts) {
            return [];
        }

        $day = (int) $parts['day'];
        $month = (int) $parts['month'];

        if ($period === 'monthly') {
            for ($i = 0; $i < 3; $i++) {
                $base = $start->copy()->addMonths($i)->startOfMonth();
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = $base->copy()->day($effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
                }
            }
            return $list;
        }

        if ($period === 'quarterly') {
            $monthPositionInQuarter = (($month - 1) % 3) + 1;
            $targetMonths = [$monthPositionInQuarter, $monthPositionInQuarter + 3, $monthPositionInQuarter + 6, $monthPositionInQuarter + 9];
            for ($yOffset = 0; $yOffset < 3; $yOffset++) {
                $year = $start->year + $yOffset;
                foreach ($targetMonths as $m) {
                    $base = Carbon::create($year, $m, 1);
                    $effectiveDay = min($day, $base->daysInMonth);
                    $occurrence = Carbon::create($year, $m, $effectiveDay)->setTimeFromTimeString($timeString);
                    if ($occurrence->gte($start)) {
                        $list[] = $occurrence;
                    }
                }
            }
            return $list;
        }

        if ($period === 'yearly') {
            for ($y = 0; $y < 2; $y++) {
                $year = $start->year + $y;
                $base = Carbon::create($year, $month, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = Carbon::create($year, $month, $effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
                }
            }
            return $list;
        }

        return [];
    }

    private function createBatchOccurrencesForNewSeries(ClubActivity $firstActivity, int $userId): void
    {
        $seriesId = $firstActivity->recurrence_series_id;
        if (!$seriesId) {
            return;
        }

        $startTimes = $this->generateOccurrenceStartTimesForPeriod($firstActivity);
        $firstStart = $firstActivity->start_time ? $firstActivity->start_time->copy()->startOfSecond() : null;

        foreach ($startTimes as $nextStartTime) {
            $nextStart = $nextStartTime->copy()->startOfSecond();
            if ($firstStart && $nextStart->eq($firstStart)) {
                continue;
            }

            $existing = ClubActivity::where('club_id', $firstActivity->club_id)
                ->where('recurrence_series_id', $seriesId)
                ->whereBetween('start_time', [$nextStart->copy(), $nextStart->copy()->endOfMinute()])
                ->exists();

            if (!$existing) {
                $this->createNextOccurrence($firstActivity, $nextStartTime, $userId, $seriesId);
            }
        }
    }

    public function updateActivity(ClubActivity $activity, array $data, int $userId): ClubActivity
    {
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]) || $activity->created_by !== $userId) {
            throw new \Exception('Không có quyền cập nhật hoạt động này');
        }

        if ($activity->status === ClubActivityStatus::Cancelled) {
            throw new \Exception('Không thể cập nhật sự kiện đã bị hủy');
        }

        if ($activity->status === ClubActivityStatus::Completed) {
            throw new \Exception('Không thể cập nhật sự kiện đã hoàn thành');
        }

        if (isset($data['duration']) && isset($data['start_time'])) {
            $startTime = Carbon::parse($data['start_time']);
            $data['end_time'] = $startTime->copy()->addMinutes($data['duration']);
        }

        if (isset($data['cancellation_deadline_minutes']) || isset($data['cancellation_deadline_hours'])) {
            $startTime = Carbon::parse($data['start_time'] ?? $activity->start_time);
            if (isset($data['cancellation_deadline_minutes'])) {
                $data['cancellation_deadline'] = $startTime->copy()->subMinutes($data['cancellation_deadline_minutes']);
            } else {
                $data['cancellation_deadline'] = $startTime->copy()->subHours($data['cancellation_deadline_hours']);
            }
        }

        if (isset($data['qr_image']) && $data['qr_image'] instanceof UploadedFile) {
            $data['qr_code_url'] = $this->imageService->optimizeThumbnail($data['qr_image'], 'activity_qr_codes', 90);
        }

        unset($data['cancellation_deadline_hours'], $data['cancellation_deadline_minutes']);
        unset($data['qr_image']);

        // DB columns fee_amount, guest_fee, penalty_amount are NOT NULL - normalize null to 0
        foreach (['fee_amount', 'guest_fee', 'penalty_amount'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }

        $editScope = $data['edit_scope'] ?? 'this_occurrence';
        unset($data['edit_scope']);

        if ($editScope === 'entire_series' && $activity->recurrence_series_id) {
            return $this->updateActivityAsNewSeries($activity, $data, $userId);
        }

        if ($editScope === 'this_occurrence') {
            unset($data['recurring_schedule']);
            DB::transaction(function () use ($activity, $data) {
                $activity->update($data);
            });
            return $activity->fresh();
        }

        $newRecurringSchedule = $data['recurring_schedule'] ?? null;
        $seriesId = $activity->recurrence_series_id;
        DB::transaction(function () use ($activity, $data, $newRecurringSchedule, $seriesId, $userId) {
            $activity->update($data);
            if ($seriesId && $newRecurringSchedule !== null && isset($newRecurringSchedule['period'])) {
                $this->syncRecurrenceSeriesAfterScheduleChange($activity, $newRecurringSchedule, $userId);
            }
        });
        return $activity->fresh();
    }

    /**
     * Sửa cả chuỗi: hủy chuỗi cũ, tạo chuỗi mới với thông tin đã sửa.
     */
    private function updateActivityAsNewSeries(ClubActivity $activity, array $data, int $userId): ClubActivity
    {
        $club = $activity->club;
        $seriesId = $activity->recurrence_series_id;

        $payload = [
            'title' => $data['title'] ?? $activity->title,
            'description' => $data['description'] ?? $activity->description,
            'type' => $data['type'] ?? $activity->type ?? 'other',
            'start_time' => $data['start_time'] ?? $activity->start_time?->format('Y-m-d H:i:s'),
            'duration' => $data['duration'] ?? $activity->duration,
            'address' => $data['address'] ?? $activity->address,
            'latitude' => $data['latitude'] ?? $activity->latitude,
            'longitude' => $data['longitude'] ?? $activity->longitude,
            'cancellation_deadline_hours' => isset($data['cancellation_deadline_hours']) ? $data['cancellation_deadline_hours'] : null,
            'cancellation_deadline_minutes' => $data['cancellation_deadline_minutes'] ?? null,
            'mini_tournament_id' => $data['mini_tournament_id'] ?? $activity->mini_tournament_id,
            'recurring_schedule' => $data['recurring_schedule'] ?? $activity->getRecurringScheduleRaw(),
            'reminder_minutes' => $data['reminder_minutes'] ?? $activity->reminder_minutes ?? 15,
            'fee_amount' => $data['fee_amount'] ?? $activity->fee_amount ?? 0,
            'fee_description' => $data['fee_description'] ?? $activity->fee_description,
            'guest_fee' => $data['guest_fee'] ?? $activity->guest_fee ?? 0,
            'penalty_amount' => $data['penalty_amount'] ?? $activity->penalty_amount ?? 0,
            'fee_split_type' => $data['fee_split_type'] ?? $activity->fee_split_type?->value ?? 'fixed',
            'allow_member_invite' => isset($data['allow_member_invite']) ? (bool) $data['allow_member_invite'] : (bool) $activity->allow_member_invite,
            'is_public' => isset($data['is_public']) ? (bool) $data['is_public'] : (bool) $activity->is_public,
            'max_participants' => $data['max_participants'] ?? $activity->max_participants,
            'creator_always_join' => isset($data['creator_always_join']) ? (bool) $data['creator_always_join'] : (bool) $activity->creator_always_join,
        ];

        return DB::transaction(function () use ($club, $activity, $seriesId, $payload, $userId) {
            $this->replaceSeriesKeepingHistory($club, $seriesId);
            return $this->createActivity($club, $payload, $userId);
        });
    }

    /**
     * Khi sửa cả chuỗi: xóa hết bản ghi cũ trạng thái scheduled/ongoing (không còn hiển thị),
     * chỉ giữ lại bản ghi đã hoàn thành hoặc đã hủy để xem lịch sử; đánh dấu chuỗi đã thay thế.
     */
    private function replaceSeriesKeepingHistory(Club $club, string $seriesId): void
    {
        $now = Carbon::now();

        ClubActivity::where('club_id', $club->id)
            ->where('recurrence_series_id', $seriesId)
            ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
            ->delete();

        ClubActivity::where('club_id', $club->id)
            ->where('recurrence_series_id', $seriesId)
            ->update(['recurrence_series_cancelled_at' => $now]);
    }

    private function syncRecurrenceSeriesAfterScheduleChange(ClubActivity $updatedActivity, array $newSchedule, int $userId): void
    {
        $seriesId = $updatedActivity->recurrence_series_id;
        if (!$seriesId) {
            return;
        }

        $now = Carbon::now();
        $period = $newSchedule['period'] ?? null;

        $futureInSeries = ClubActivity::where('recurrence_series_id', $seriesId)
            ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
            ->where('start_time', '>', $now)
            ->get();

        foreach ($futureInSeries as $a) {
            $a->update(['recurring_schedule' => $newSchedule]);
        }

        if ($period === 'weekly' && !empty($newSchedule['week_days'])) {
            $allowedWeekDays = array_map('intval', (array) $newSchedule['week_days']);
            foreach ($futureInSeries as $a) {
                $dayOfWeek = (int) Carbon::parse($a->start_time)->dayOfWeek;
                if (!in_array($dayOfWeek, $allowedWeekDays, true)) {
                    $a->update([
                        'status' => ClubActivityStatus::Cancelled,
                        'cancellation_reason' => 'Lịch lặp đã thay đổi, ngày này không còn trong chu kỳ.',
                        'cancelled_by' => $userId,
                    ]);
                }
            }
        }
    }

    public function deleteActivity(ClubActivity $activity, int $userId): void
    {
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary]) || $activity->created_by !== $userId) {
            throw new \Exception('Không có quyền xóa hoạt động này');
        }

        if (!$activity->canBeCancelled()) {
            throw new \Exception('Chỉ có thể xóa hoạt động đang scheduled');
        }

        $activity->delete();
    }

    public function completeActivity(ClubActivity $activity, int $userId): ClubActivity
    {
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền đánh dấu hoàn thành');
        }

        if (!$activity->isScheduled() && !$activity->isOngoing()) {
            throw new \Exception('Chỉ có thể đánh dấu hoàn thành sự kiện đang scheduled hoặc ongoing');
        }

        $activity->markAsCompleted();

        $this->notifyCreatorToCollectFees($activity);

        if ($activity->isRecurring()) {
            $this->ensureNextOccurrenceExists($activity, $userId);
        }

        return $activity;
    }

    public function cancelActivity(
        ClubActivity $activity,
        int $userId,
        ?string $cancellationReason = null,
        ?bool $cancelTransactions = false
    ): ClubActivity {
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền hủy sự kiện');
        }

        if (!$activity->canBeCancelled()) {
            throw new \Exception('Chỉ có thể hủy sự kiện đang scheduled hoặc ongoing');
        }

        return DB::transaction(function () use ($activity, $club, $userId, $cancellationReason, $cancelTransactions) {
            $activity->update([
                'status' => ClubActivityStatus::Cancelled,
                'cancellation_reason' => $cancellationReason ?? 'Không có lý do cụ thể',
                'cancelled_by' => $userId,
            ]);

            if ($cancelTransactions) {
                $mainWallet = $club->mainWallet;
                if (!$mainWallet) {
                    throw new \Exception('CLB chưa có ví chính');
                }

                $participants = $activity->acceptedParticipants()
                    ->with(['user', 'walletTransaction'])
                    ->get();

                foreach ($participants as $participant) {
                    $transaction = $participant->walletTransaction;

                    if ($transaction) {
                        if ($transaction->isConfirmed()) {
                            ClubWalletTransaction::create([
                                'club_wallet_id' => $mainWallet->id,
                                'direction' => ClubWalletTransactionDirection::Out,
                                'amount' => $transaction->amount,
                                'source_type' => ClubWalletTransactionSourceType::Activity,
                                'source_id' => $activity->id,
                                'payment_method' => PaymentMethod::BankTransfer,
                                'status' => ClubWalletTransactionStatus::Pending,
                                'description' => "Hoàn tiền cho {$participant->user->full_name} do hủy sự kiện: {$activity->title}",
                                'created_by' => $userId,
                            ]);
                        }

                        $transaction->reject($userId);
                    }
                }
            }

            $participantsToNotify = $activity->participants()
                ->whereIn('status', [ClubActivityParticipantStatus::Accepted, ClubActivityParticipantStatus::Attended])
                ->with('user')
                ->get();

            $message = "Sự kiện {$activity->title} tại CLB {$club->name} đã bị hủy";
            foreach ($participantsToNotify as $participant) {
                $user = $participant->user;
                if ($user) {
                    $user->notify(new ClubActivityCancelledNotification($club, $activity));
                    SendPushJob::dispatch($user->id, 'Sự kiện đã bị hủy', $message, [
                        'type' => 'CLUB_ACTIVITY_CANCELLED',
                        'club_id' => (string) $club->id,
                        'club_activity_id' => (string) $activity->id,
                    ]);
                }
            }

            if ($activity->isRecurring()) {
                $this->ensureNextOccurrenceForCancelled($activity);
            }

            return $activity;
        });
    }

    public function ensureNextOccurrenceForCancelled(ClubActivity $cancelledActivity): void
    {
        if (!$cancelledActivity->isRecurring()) {
            return;
        }
        $userId = $cancelledActivity->created_by ?? 0;
        if ($userId > 0) {
            $this->ensureNextOccurrenceExists($cancelledActivity, $userId);
        }
    }

    public function ensureNextOccurrenceForCompleted(ClubActivity $completedActivity): void
    {
        if (!$completedActivity->isRecurring()) {
            return;
        }
        $userId = $completedActivity->created_by ?? 0;
        if ($userId > 0) {
            $this->ensureNextOccurrenceExists($completedActivity, $userId);
        }
    }

    private function ensureNextOccurrenceExists(ClubActivity $completedActivity, int $userId): void
    {
        $fromDate = $completedActivity->end_time ?? $completedActivity->start_time;
        if (!$fromDate) {
            return;
        }

        $nextStartTime = $completedActivity->calculateNextOccurrence($fromDate);

        if (!$nextStartTime) {
            return;
        }

        if ($nextStartTime->lte($fromDate)) {
            $nextStartTime = $completedActivity->calculateNextOccurrence($nextStartTime->copy()->addMinute());
            if (!$nextStartTime) {
                return;
            }
        }

        $seriesId = $completedActivity->recurrence_series_id;
        $existingQuery = ClubActivity::where('club_id', $completedActivity->club_id)
            ->whereBetween('start_time', [
                $nextStartTime->copy()->startOfMinute(),
                $nextStartTime->copy()->endOfMinute(),
            ]);
        if ($seriesId) {
            $existingQuery->where('recurrence_series_id', $seriesId);
        } else {
            $rawSchedule = $completedActivity->getRecurringScheduleRaw();
            if ($rawSchedule) {
                $rawSchedule = json_encode($rawSchedule);
            }
            $existingQuery->where('title', $completedActivity->title)
                ->where('recurring_schedule', $rawSchedule);
        }
        if ($existingQuery->exists()) {
            return;
        }

        $this->createNextOccurrence($completedActivity, $nextStartTime, $userId, $seriesId);
    }

    private function generateInitialOccurrences(ClubActivity $activity, int $userId): int
    {
        $count = 0;
        $maxIterations = 100;
        $iteration = 0;
        $lookAheadDate = Carbon::now()->addDays(30);

        $fromDate = $activity->end_time ?? $activity->start_time;

        while ($iteration < $maxIterations) {
            $iteration++;

            $nextStartTime = $activity->calculateNextOccurrence($fromDate);

            if (!$nextStartTime || $nextStartTime->gt($lookAheadDate)) {
                break;
            }

            if ($nextStartTime->lte($fromDate)) {
                $fromDate = $nextStartTime->copy()->addMinute();
                continue;
            }

            $rawSchedule = $activity->getRecurringScheduleRaw();
            if ($rawSchedule) {
                $rawSchedule = json_encode($rawSchedule);
            }
            $existing = ClubActivity::where('club_id', $activity->club_id)
                ->where('title', $activity->title)
                ->where('recurring_schedule', $rawSchedule)
                ->whereBetween('start_time', [
                    $nextStartTime->copy()->startOfMinute(),
                    $nextStartTime->copy()->endOfMinute(),
                ])
                ->exists();

            if (!$existing) {
                $this->createNextOccurrence($activity, $nextStartTime, $userId, $activity->recurrence_series_id);
                $count++;
            }

            $fromDate = $nextStartTime->copy()->addMinute();
        }

        return $count;
    }

    private function createNextOccurrence(ClubActivity $activity, Carbon $nextStartTime, int $userId, ?string $recurrenceSeriesId = null): ClubActivity
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

        $seriesId = $recurrenceSeriesId ?? $activity->recurrence_series_id;

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
        $newActivity->recurrence_series_id = $seriesId;
        $newActivity->recurrence_series_cancelled_at = null;
        $newActivity->save();

        if ($activity->creator_always_join) {
            ClubActivityParticipant::create([
                'club_activity_id' => $newActivity->id,
                'user_id' => $userId,
                'status' => ClubActivityParticipantStatus::Accepted,
            ]);
        }

        $checkInToken = Str::random(48);
        $newActivity->update(['check_in_token' => $checkInToken]);

        return $newActivity;
    }

    public function buildCheckInUrl(int $clubId, int $activityId, string $token): string
    {
        return url("/clubs/{$clubId}/detail-activity?activityId={$activityId}&token={$token}");
    }

    public function generateOccurrenceStartTimesForRollover(ClubActivity $template, Carbon $afterDate): array
    {
        $schedule = $template->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['period'])) {
            return [];
        }

        $timeString = $template->start_time ? $template->start_time->format('H:i:s') : '00:00:00';
        $period = $schedule['period'];
        $list = [];

        if ($period === 'weekly') {
            $weekDays = $schedule['week_days'] ?? [];
            if (empty($weekDays)) {
                return [];
            }
            $nextMonthStart = $afterDate->copy()->addMonth()->startOfMonth();
            $monthEnd = $nextMonthStart->copy()->endOfMonth();
            for ($d = $nextMonthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                if (in_array((int) $d->dayOfWeek, array_map('intval', $weekDays), true)) {
                    $d->setTimeFromTimeString($timeString);
                    $list[] = $d->copy();
                }
            }
            return $list;
        }

        $parts = $template->getRecurringDateParts();
        if (!$parts) {
            return [];
        }
        $day = (int) $parts['day'];
        $month = (int) $parts['month'];

        if ($period === 'monthly') {
            $startFrom = $afterDate->copy()->addMonth()->startOfMonth();
            for ($i = 0; $i < 3; $i++) {
                $base = $startFrom->copy()->addMonths($i);
                $effectiveDay = min($day, $base->daysInMonth);
                $list[] = $base->copy()->day($effectiveDay)->setTimeFromTimeString($timeString);
            }
            return $list;
        }

        if ($period === 'quarterly') {
            $monthPositionInQuarter = (($month - 1) % 3) + 1;
            $targetMonths = [$monthPositionInQuarter, $monthPositionInQuarter + 3, $monthPositionInQuarter + 6, $monthPositionInQuarter + 9];
            $year = $afterDate->year + 1;
            foreach ($targetMonths as $m) {
                $base = Carbon::create($year, $m, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $list[] = Carbon::create($year, $m, $effectiveDay)->setTimeFromTimeString($timeString);
            }
            return $list;
        }

        if ($period === 'yearly') {
            $lastYear = $afterDate->year;
            for ($y = 1; $y <= 2; $y++) {
                $year = $lastYear + $y;
                $base = Carbon::create($year, $month, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $list[] = Carbon::create($year, $month, $effectiveDay)->setTimeFromTimeString($timeString);
            }
            return $list;
        }

        return [];
    }

    public function rolloverRecurrenceSeries(): int
    {
        $seriesIds = ClubActivity::whereNotNull('recurrence_series_id')
            ->whereNull('recurrence_series_cancelled_at')
            ->distinct()
            ->pluck('recurrence_series_id');

        $created = 0;
        foreach ($seriesIds as $seriesId) {
            $lastActivity = ClubActivity::where('recurrence_series_id', $seriesId)
                ->orderByDesc('start_time')
                ->first();
            if (!$lastActivity || !$lastActivity->isRecurring()) {
                continue;
            }

            $lastStart = $lastActivity->start_time;
            if (!$lastStart) {
                continue;
            }

            $schedule = $lastActivity->getRecurringScheduleRaw();
            $period = $schedule['period'] ?? null;
            $periodEnd = match ($period) {
                'weekly' => $lastStart->copy()->endOfMonth(),
                'monthly' => $lastStart->copy()->addMonths(2)->endOfMonth(),
                'quarterly' => Carbon::create($lastStart->year, 12, 31)->endOfDay(),
                'yearly' => Carbon::create($lastStart->year + 1, 12, 31)->endOfDay(),
                default => null,
            };

            if (!$periodEnd || Carbon::now()->lte($periodEnd)) {
                continue;
            }

            $startTimes = $this->generateOccurrenceStartTimesForRollover($lastActivity, $lastStart);
            $userId = $lastActivity->created_by ?? 0;
            if ($userId < 1) {
                continue;
            }

            foreach ($startTimes as $nextStartTime) {
                $nextStart = $nextStartTime->copy()->startOfMinute();
                $existing = ClubActivity::where('recurrence_series_id', $seriesId)
                    ->whereBetween('start_time', [$nextStart, $nextStart->copy()->endOfMinute()])
                    ->exists();
                if (!$existing) {
                    $this->createNextOccurrence($lastActivity, $nextStartTime, $userId, $seriesId);
                    $created++;
                }
            }
        }

        return $created;
    }

    public function cancelRecurrenceSeries(Club $club, string $seriesIdOrActivityId, int $userId): int
    {
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            throw new \Exception('Chỉ admin/manager/secretary mới có quyền hủy chuỗi hoạt động');
        }

        $seriesId = Str::isUuid($seriesIdOrActivityId)
            ? $seriesIdOrActivityId
            : ClubActivity::where('club_id', $club->id)->where('id', $seriesIdOrActivityId)->value('recurrence_series_id');

        if (!$seriesId) {
            throw new \Exception('Chuỗi hoạt động không tồn tại');
        }

        $now = Carbon::now();

        $count = ClubActivity::where('club_id', $club->id)
            ->where('recurrence_series_id', $seriesId)
            ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
            ->where('start_time', '>', $now)
            ->count();

        DB::transaction(function () use ($club, $seriesId, $userId, $now) {
            ClubActivity::where('club_id', $club->id)
                ->where('recurrence_series_id', $seriesId)
                ->whereIn('status', [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing])
                ->where('start_time', '>', $now)
                ->update([
                    'status' => ClubActivityStatus::Cancelled,
                    'cancellation_reason' => 'Hủy cả chuỗi lặp lại',
                    'cancelled_by' => $userId,
                    'recurrence_series_cancelled_at' => $now,
                ]);
        });

        return $count;
    }

    public function notifyCreatorToCollectFees(ClubActivity $activity): void
    {
        if ((float) $activity->fee_amount <= 0) {
            return;
        }

        if ($activity->fee_split_type === ClubActivityFeeSplitType::Fund) {
            return;
        }

        if ($activity->has_transaction) {
            return;
        }

        $creatorId = $activity->created_by;
        if (!$creatorId) {
            return;
        }

        $user = User::find($creatorId);
        if (!$user) {
            return;
        }

        $club = $activity->club;
        $message = "Hoạt động {$activity->title} đã hoàn thành. Hãy vào chốt bill thu tiền sự kiện tại CLB {$club->name}";

        $user->notify(new ClubActivityCompletedFeeReminderNotification($club, $activity));
        SendPushJob::dispatch($user->id, 'Nhắc thu tiền sự kiện', $message, [
            'type' => 'CLUB_ACTIVITY_COMPLETED_FEE_REMINDER',
            'club_id' => (string) $club->id,
            'club_activity_id' => (string) $activity->id,
        ]);
    }
}
