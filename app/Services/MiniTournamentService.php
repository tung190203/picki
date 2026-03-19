<?php

namespace App\Services;

use App\Models\MiniTournament;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournamentStaff;
use App\Enums\PaymentStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MiniTournamentService
{
    public function createTournament(array $data, int $userId): MiniTournament
    {
        $recurringSchedule = $data['recurring_schedule'] ?? null;
        $seriesId = $recurringSchedule ? Str::uuid()->toString() : null;

        // Ensure fee_amount is not null (default to 0)
        if (!isset($data['fee_amount']) || $data['fee_amount'] === null) {
            $data['fee_amount'] = 0;
        }

        Log::info('MiniTournamentService::createTournament', [
            'has_recurring' => !empty($recurringSchedule),
            'series_id' => $seriesId,
        ]);

        $miniTournament = MiniTournament::create([
            ...$data,
            'recurrence_series_id' => $seriesId,
        ]);

        // Creator always participates by default with confirmed payment status
        // (creator is exempt from payment or auto-confirmed)
        $participant = MiniParticipant::create([
            'mini_tournament_id' => $miniTournament->id,
            'user_id' => $userId,
            'is_confirmed' => true,
            'is_invited' => false,
            'payment_status' => PaymentStatusEnum::CONFIRMED,
        ]);

        // Tạo khoản thu cho chủ kèo nếu kèo có thu phí
        // Nếu auto_split_fee = true, chỉ tạo payment khi kèo kết thúc (via command)
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
            $feePerPerson = $miniTournament->fee_amount;

            MiniParticipantPayment::create([
                'mini_tournament_id' => $miniTournament->id,
                'participant_id' => $participant->id,
                'user_id' => $userId,
                'amount' => $feePerPerson,
                'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                'paid_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => $userId,
            ]);
        }

        // Tạo batch occurrences nếu là recurring
        if ($miniTournament->isRecurring() && $seriesId) {
            $this->createBatchOccurrencesForNewSeries($miniTournament, $userId);
        }

        return $miniTournament;
    }

    public function generateOccurrenceStartTimesForPeriod(MiniTournament $tournament): array
    {
        $schedule = $tournament->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['period'])) {
            return [];
        }

        $start = $tournament->start_time ? Carbon::parse($tournament->start_time) : Carbon::now();
        $timeString = $start->format('H:i:s');
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

        $parts = $tournament->getRecurringDateParts();
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
            $year = $start->year;
            foreach ($targetMonths as $m) {
                $base = Carbon::create($year, $m, 1);
                $effectiveDay = min($day, $base->daysInMonth);
                $occurrence = Carbon::create($year, $m, $effectiveDay)->setTimeFromTimeString($timeString);
                if ($occurrence->gte($start)) {
                    $list[] = $occurrence;
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

    private function createBatchOccurrencesForNewSeries(MiniTournament $firstTournament, int $userId): void
    {
        $seriesId = $firstTournament->recurrence_series_id;
        if (!$seriesId) {
            return;
        }

        $startTimes = $this->generateOccurrenceStartTimesForPeriod($firstTournament);
        $firstStart = $firstTournament->start_time ? Carbon::parse($firstTournament->start_time)->copy()->startOfMinute() : null;

        foreach ($startTimes as $nextStartTime) {
            $nextStart = $nextStartTime->copy()->startOfMinute();
            if ($firstStart && $nextStart->eq($firstStart)) {
                continue;
            }

            $this->createNextOccurrenceIfMissing($firstTournament, $nextStartTime, $userId, $seriesId);
        }
    }

    public function createNextOccurrenceIfMissing(
        MiniTournament $tournament,
        Carbon $nextStartTime,
        int $userId,
        ?string $recurrenceSeriesId = null
    ): ?MiniTournament {
        $seriesId = $recurrenceSeriesId ?? $tournament->recurrence_series_id;
        if (!$seriesId) {
            return null;
        }

        $nextStart = $nextStartTime->copy()->startOfMinute();
        $exists = MiniTournament::where('recurrence_series_id', $seriesId)
            ->whereBetween('start_time', [$nextStart->copy(), $nextStart->copy()->endOfMinute()])
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->createNextOccurrence($tournament, $nextStartTime, $userId, $seriesId);
    }

    private function createNextOccurrence(MiniTournament $tournament, Carbon $nextStartTime, int $userId, ?string $recurrenceSeriesId = null): MiniTournament
    {
        $duration = $tournament->duration ?? ($tournament->end_time ? $tournament->start_time->diffInMinutes($tournament->end_time) : null);
        $nextEndTime = $duration ? $nextStartTime->copy()->addMinutes($duration) : null;

        $seriesId = $recurrenceSeriesId ?? $tournament->recurrence_series_id;

        // Replicate tournament but exclude only status and recurrence_series_cancelled_at
        // This ensures poster and qr_code_url are copied to the new occurrence
        $newTournament = $tournament->replicate([
            'status',
            'recurrence_series_cancelled_at',
        ]);

        $newTournament->start_time = $nextStartTime;
        $newTournament->end_time = $nextEndTime;
        $newTournament->recurrence_series_id = $seriesId;
        $newTournament->recurrence_series_cancelled_at = null;
        $newTournament->save();

        $this->syncStaffAndCreatorForOccurrence($tournament, $newTournament, $userId);

        return $newTournament;
    }

    private function syncStaffAndCreatorForOccurrence(MiniTournament $source, MiniTournament $target, int $userId): void
    {
        $source->loadMissing(['miniTournamentStaffs', 'participants']);

        // Copy staff roles from source occurrence.
        foreach ($source->miniTournamentStaffs as $staff) {
            MiniTournamentStaff::firstOrCreate([
                'mini_tournament_id' => $target->id,
                'user_id' => $staff->user_id,
                'role' => $staff->role,
            ]);
        }

        // If source has no organizer staff, fallback attach creator as organizer.
        $hasOrganizer = $target->miniTournamentStaffs()
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->exists();

        if (!$hasOrganizer && $userId > 0) {
            MiniTournamentStaff::firstOrCreate([
                'mini_tournament_id' => $target->id,
                'user_id' => $userId,
                'role' => MiniTournamentStaff::ROLE_ORGANIZER,
            ]);
        }

        // Creator should always be a confirmed participant in each occurrence.
        $creatorParticipant = MiniParticipant::firstOrCreate(
            [
                'mini_tournament_id' => $target->id,
                'user_id' => $userId,
            ],
            [
                'is_confirmed' => true,
                'is_invited' => false,
                'payment_status' => PaymentStatusEnum::CONFIRMED,
            ]
        );

        if ($target->has_fee && !$target->auto_split_fee) {
            MiniParticipantPayment::firstOrCreate(
                [
                    'mini_tournament_id' => $target->id,
                    'participant_id' => $creatorParticipant->id,
                ],
                [
                    'user_id' => $userId,
                    'amount' => $target->fee_amount ?? 0,
                    'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                    'paid_at' => now(),
                    'confirmed_at' => now(),
                    'confirmed_by' => $userId,
                ]
            );
        }
    }

    public function cancelRecurrenceSeries(string $seriesIdOrTournamentId, int $userId): int
    {
        $seriesId = Str::isUuid($seriesIdOrTournamentId)
            ? $seriesIdOrTournamentId
            : MiniTournament::where('id', $seriesIdOrTournamentId)->value('recurrence_series_id');

        if (!$seriesId) {
            throw new \Exception('Chuỗi kèo đấu không tồn tại');
        }

        $tournamentIds = MiniTournament::where('recurrence_series_id', $seriesId)
            ->pluck('id')
            ->toArray();

        $hasPermission = MiniTournamentStaff::where('user_id', $userId)
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->whereIn('mini_tournament_id', $tournamentIds)
            ->exists();

        if (!$hasPermission) {
            throw new \Exception('Chỉ organizer mới có quyền hủy chuỗi kèo đấu');
        }

        $now = Carbon::now();

        $candidates = MiniTournament::where('recurrence_series_id', $seriesId)
            ->where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->where('start_time', '>', $now)
            ->get();

        $deleteIds = [];
        foreach ($candidates as $tournament) {
            if (!$tournament->allow_cancellation) {
                continue;
            }
            if ($tournament->isCancellationClosed($now)) {
                continue;
            }
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $tournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();
            if ($hasCompletedMatch) {
                continue;
            }
            $deleteIds[] = $tournament->id;
        }

        DB::transaction(function () use ($deleteIds, $seriesId, $now) {
            if (!empty($deleteIds)) {
                MiniTournament::whereIn('id', $deleteIds)->delete();
            }

            // Đánh dấu chuỗi đã bị hủy (ngăn không cho tạo kèo mới)
            MiniTournament::where('recurrence_series_id', $seriesId)
                ->update(['recurrence_series_cancelled_at' => $now]);
        });

        return count($deleteIds);
    }
}
