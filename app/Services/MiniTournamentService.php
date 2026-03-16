<?php

namespace App\Services;

use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Enums\PaymentStatusEnum;
use Carbon\Carbon;
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

        \Log::info('MiniTournamentService::createTournament', [
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

            $existing = MiniTournament::where('recurrence_series_id', $seriesId)
                ->whereBetween('start_time', [$nextStart->copy(), $nextStart->copy()->endOfMinute()])
                ->exists();

            if (!$existing) {
                $this->createNextOccurrence($firstTournament, $nextStartTime, $userId, $seriesId);
            }
        }
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

        return $newTournament;
    }
}
