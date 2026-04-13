<?php

namespace App\Models\Club;

use App\Enums\ClubActivityFeeSplitType;
use App\Enums\ClubActivityParticipantStatus;
use App\Enums\ClubActivityStatus;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Models\User;
use App\Models\MiniTournament;
use App\Models\Club\ClubWalletTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ClubActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'mini_tournament_id',
        'title',
        'description',
        'type',
        'recurring_schedule',
        'recurrence_series_id',
        'recurrence_series_cancelled_at',
        'start_time',
        'end_time',
        'duration',
        'address',
        'latitude',
        'longitude',
        'cancellation_deadline',
        'reminder_minutes',
        'status',
        'created_by',
        'cancellation_reason',
        'cancelled_by',
        'fee_amount',
        'fee_description',
        'guest_fee',
        'penalty_amount',
        'fee_split_type',
        'allow_member_invite',
        'is_public',
        'max_participants',
        'qr_code_url',
        'check_in_token',
        'creator_always_join',
        'has_transaction',
    ];

    protected $casts = [
        'status' => ClubActivityStatus::class,
        'fee_split_type' => ClubActivityFeeSplitType::class,
        'recurrence_series_cancelled_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancellation_deadline' => 'datetime',
        'fee_amount' => 'decimal:2',
        'guest_fee' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
        'allow_member_invite' => 'boolean',
        'is_public' => 'boolean',
        'creator_always_join' => 'boolean',
        'has_transaction' => 'boolean',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class);
    }

    public function participants()
    {
        return $this->hasMany(ClubActivityParticipant::class);
    }

    public function activityFeeTransactions()
    {
        return $this->hasMany(ClubWalletTransaction::class, 'source_id')
            ->where('source_type', ClubWalletTransactionSourceType::Activity)
            ->where('direction', ClubWalletTransactionDirection::In)
            ->where('status', ClubWalletTransactionStatus::Confirmed);
    }

    public function fundCollection()
    {
        return $this->hasOne(ClubFundCollection::class, 'club_activity_id');
    }

    public function acceptedParticipants()
    {
        return $this->hasMany(ClubActivityParticipant::class)->where('status', ClubActivityParticipantStatus::Accepted);
    }

    public function attendedParticipants()
    {
        return $this->hasMany(ClubActivityParticipant::class)->where('status', ClubActivityParticipantStatus::Attended);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', ClubActivityStatus::Scheduled);
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', ClubActivityStatus::Ongoing);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ClubActivityStatus::Completed);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('start_time', [$from, $to]);
    }

    public function isScheduled()
    {
        return $this->status === ClubActivityStatus::Scheduled;
    }

    public function isOngoing()
    {
        return $this->status === ClubActivityStatus::Ongoing;
    }

    public function isCompleted()
    {
        return $this->status === ClubActivityStatus::Completed;
    }

    public function canBeCancelled()
    {
        return in_array($this->status, [ClubActivityStatus::Scheduled, ClubActivityStatus::Ongoing]);
    }

    public function markAsCompleted()
    {
        $this->update(['status' => ClubActivityStatus::Completed]);
    }

    public function getFeeAmountPerParticipant(): float
    {
        $splitType = $this->fee_split_type ?? ClubActivityFeeSplitType::Fixed;

        if ($splitType === ClubActivityFeeSplitType::Fund) {
            return 0;
        }

        if ((float) $this->fee_amount <= 0) {
            return 0;
        }

        if ($splitType === ClubActivityFeeSplitType::Equal) {
            $n = max(1, (int) ($this->max_participants ?? 1));
            return round((float) $this->fee_amount / $n, 2);
        }

        return (float) $this->fee_amount;
    }

    public function isRecurring(): bool
    {
        return $this->recurring_schedule !== null && !empty($this->recurring_schedule);
    }

    public function isRecurrenceSeriesCancelled(): bool
    {
        return $this->recurrence_series_cancelled_at !== null;
    }

    public function scopeInRecurrenceSeries($query, string $seriesId)
    {
        return $query->where('recurrence_series_id', $seriesId);
    }

    public function getCancellationDeadlineMinutesAttribute(): ?int
    {
        if (!$this->cancellation_deadline || !$this->start_time) {
            return null;
        }
        $minutes = $this->cancellation_deadline->diffInMinutes($this->start_time, false);
        return $minutes > 0 ? (int) $minutes : null;
    }

    public function getCancellationDeadlineHoursAttribute(): ?float
    {
        $minutes = $this->cancellation_deadline_minutes;
        return $minutes !== null ? round($minutes / 60, 1) : null;
    }

    public function getRecurringScheduleAttribute($value)
    {
        if (!$value) {
            return null;
        }

        $data = json_decode($value, true);
        if (!$data || !isset($data['period'])) {
            return null;
        }

        $result = [
            'period' => $data['period'],
            'week_days' => null,
            'recurring_date' => null,
        ];

        if ($data['period'] === 'weekly') {
            $result['week_days'] = $data['week_days'] ?? null;
        } elseif (isset($data['recurring_date'])) {
            $result['recurring_date'] = is_string($data['recurring_date'])
                ? $data['recurring_date']
                : (string) $data['recurring_date'];
        }

        return $result;
    }

    public function setRecurringScheduleAttribute($value)
    {
        if (!$value) {
            $this->attributes['recurring_schedule'] = null;
            return;
        }

        $this->attributes['recurring_schedule'] = json_encode($value);
    }

    private function parseDate(string $dateString): ?array
    {
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd-m-Y H:i:s'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date && $date->format($format) === $dateString) {
                    return [
                        'day' => $date->day,
                        'month' => $date->month,
                        'year' => $date->year,
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    public function getRecurringScheduleRaw(): ?array
    {
        $value = $this->attributes['recurring_schedule'] ?? null;
        if (!$value) {
            return null;
        }

        $data = json_decode($value, true);
        return $data && isset($data['period']) ? $data : null;
    }

    public function getRecurringDateParts(): ?array
    {
        $schedule = $this->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['recurring_date'])) {
            return null;
        }
        return $this->parseDate($schedule['recurring_date']);
    }

    public function calculateNextOccurrence(Carbon $fromDate = null): ?Carbon
    {
        if (!$this->isRecurring()) {
            return null;
        }

        $schedule = $this->getRecurringScheduleRaw();
        if (!$schedule) {
            return null;
        }

        $fromDate = $fromDate ?? Carbon::now();
        $period = $schedule['period'];

        return match($period) {
            'weekly' => $this->calculateNextWeeklyOccurrence($fromDate, $schedule['week_days'] ?? []),
            'monthly' => $this->calculateNextMonthlyOccurrence($fromDate, $schedule['recurring_date'] ?? null),
            'quarterly' => $this->calculateNextQuarterlyOccurrence($fromDate, $schedule['recurring_date'] ?? null),
            'yearly' => $this->calculateNextYearlyOccurrence($fromDate, $schedule['recurring_date'] ?? null),
            default => null
        };
    }

    private function calculateNextWeeklyOccurrence(Carbon $fromDate, array $weekDays): ?Carbon
    {
        if (empty($weekDays)) {
            return null;
        }

        sort($weekDays);

        $currentDayOfWeek = $fromDate->dayOfWeek;

        $timeString = $this->start_time?->format('H:i:s') ?? $fromDate->format('H:i:s');

        foreach ($weekDays as $targetDay) {
            if ($targetDay > $currentDayOfWeek) {
                $daysToAdd = $targetDay - $currentDayOfWeek;
                return $fromDate->copy()->addDays($daysToAdd)->setTimeFromTimeString($timeString);
            }
        }

        $daysToAdd = 7 - $currentDayOfWeek + $weekDays[0];
        return $fromDate->copy()->addDays($daysToAdd)->setTimeFromTimeString($timeString);
    }

    /**
     * Tính occurrence tiếp theo cho lịch hàng tháng.
     * Nếu targetDay lớn hơn số ngày trong tháng (vd: ngày 31 mà tháng 2 chỉ có 28 ngày),
     * occurrence sẽ rơi vào ngày cuối cùng của tháng đó. Đây là intended behavior.
     */
    private function calculateNextMonthlyOccurrence(Carbon $fromDate, ?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        $dateInfo = $this->parseDate($dateString);
        if (!$dateInfo) {
            return null;
        }

        $targetDay = $dateInfo['day'];

        $nextDate = $fromDate->copy()->day(min($targetDay, $fromDate->daysInMonth));
        if ($this->start_time) {
            $nextDate->setTimeFromTimeString($this->start_time->format('H:i:s'));
        }

        if ($nextDate->lte($fromDate)) {
            $nextDate->addMonth();
            $nextDate->day(min($targetDay, $nextDate->daysInMonth));
        }

        return $nextDate;
    }

    private function calculateNextQuarterlyOccurrence(Carbon $fromDate, ?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        $dateInfo = $this->parseDate($dateString);
        if (!$dateInfo) {
            return null;
        }

        $targetDay = $dateInfo['day'];
        $selectedMonth = $dateInfo['month'];
        $monthPositionInQuarter = ((int) $selectedMonth - 1) % 3 + 1;
        $targetMonths = [$monthPositionInQuarter, $monthPositionInQuarter + 3, $monthPositionInQuarter + 6, $monthPositionInQuarter + 9];

        $timeString = $this->start_time?->format('H:i:s');
        $currentYear = $fromDate->year;
        $currentMonth = $fromDate->month;

        foreach ([$currentYear, $currentYear + 1, $currentYear + 2, $currentYear + 3] as $year) {
            foreach ($targetMonths as $m) {
                $nextDate = Carbon::create($year, $m, 1);
                $effectiveDay = min($targetDay, $nextDate->daysInMonth);
                $nextDate->day($effectiveDay);
                if ($timeString) {
                    $nextDate->setTimeFromTimeString($timeString);
                }
                if ($nextDate->gt($fromDate)) {
                    return $nextDate;
                }
            }
        }

        return null;
    }

    private function calculateNextYearlyOccurrence(Carbon $fromDate, ?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        $dateInfo = $this->parseDate($dateString);
        if (!$dateInfo) {
            return null;
        }

        $targetDay = $dateInfo['day'];
        $targetMonth = $dateInfo['month'];

        $nextDate = $fromDate->copy()
            ->month($targetMonth)
            ->day(min($targetDay, Carbon::create($fromDate->year, $targetMonth)->daysInMonth));

        if ($this->start_time) {
            $nextDate->setTimeFromTimeString($this->start_time->format('H:i:s'));
        }

        if ($nextDate->lte($fromDate)) {
            $nextDate->addYear();
        }

        return $nextDate;
    }

    public function cloneForNextOccurrence(): ?ClubActivity
    {
        if (!$this->isRecurring() || !$this->isCompleted()) {
            return null;
        }

        $nextStartTime = $this->calculateNextOccurrence($this->end_time ?? $this->start_time);
        if (!$nextStartTime) {
            return null;
        }

        $duration = $this->duration ?? ($this->end_time ? $this->start_time->diffInMinutes($this->end_time) : null);
        $nextEndTime = $duration ? $nextStartTime->copy()->addMinutes($duration) : null;

        $nextCancellationDeadline = null;
        if ($this->cancellation_deadline && $this->start_time) {
            $minutesBeforeStart = $this->cancellation_deadline->diffInMinutes($this->start_time, false);
            if ($minutesBeforeStart > 0) {
                $nextCancellationDeadline = $nextStartTime->copy()->subMinutes($minutesBeforeStart);
            }
        }

        $newActivity = $this->replicate([
            'status',
            'cancellation_reason',
            'cancelled_by',
            'check_in_token',
        ]);

        $newActivity->start_time = $nextStartTime;
        $newActivity->end_time = $nextEndTime;
        $newActivity->cancellation_deadline = $nextCancellationDeadline;
        $newActivity->status = ClubActivityStatus::Scheduled;
        $newActivity->save();

        if ($this->creator_always_join && $this->created_by) {
            ClubActivityParticipant::create([
                'club_activity_id' => $newActivity->id,
                'user_id' => $this->created_by,
                'status' => ClubActivityParticipantStatus::Accepted,
            ]);
        }

        $checkInToken = \Illuminate\Support\Str::random(48);
        $newActivity->update(['check_in_token' => $checkInToken]);

        return $newActivity;
    }
}
