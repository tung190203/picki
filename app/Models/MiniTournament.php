<?php

namespace App\Models;

use App\Models\Club\Club;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Notifications\Notifiable;

class MiniTournament extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'poster',
        'created_by',
        'sport_id',
        'club_id',
        'name',
        'description',
        'play_mode',
        'format',
        'start_time',
        'end_time',
        'duration',
        'competition_location_id',
        'is_private',
        'has_fee',
        'auto_split_fee',
        'fee_amount',
        'final_fee_per_person',
        'auto_payment_created',
        'fee_description',
        'qr_code_url',
        'payment_account_id',
        'max_players',
        'min_rating',
        'max_rating',
        'set_number',
        'base_points',
        'points_difference',
        'max_points',
        'gender',
        'auto_approve',
        'allow_participant_add_friends',
        'allow_cancellation',
        'cancellation_duration',
        'apply_rule',
        'recurring_schedule',
        'recurrence_series_id',
        'recurrence_series_cancelled_at',
        'status',
        'use_club_fund',
        'club_fund_collection_id',
        'included_in_club_fund',
    ];

    protected $casts = [
        'auto_approve' => 'boolean',
        'use_club_fund' => 'bool',
        'included_in_club_fund' => 'bool',
        'fee_amount' => 'integer',
    ];

    const PER_PAGE = 15;

    // Play Mode: 1=casual, 2=competition, 3=practice
    const PLAY_MODE_CASUAL = 1;
    const PLAY_MODE_COMPETITION = 2;
    const PLAY_MODE_PRACTICE = 3;
    const PLAY_MODE = [
        self::PLAY_MODE_CASUAL,
        self::PLAY_MODE_COMPETITION,
        self::PLAY_MODE_PRACTICE,
    ];

    // Format: 1=single, 2=double, 3=mens_doubles, 4=womens_doubles, 5=mixed
    const FORMAT_SINGLE = 1;
    const FORMAT_DOUBLE = 2;
    const FORMAT_MENS_DOUBLES = 3;
    const FORMAT_WOMENS_DOUBLES = 4;
    const FORMAT_MIXED = 5;
    const FORMAT = [
        self::FORMAT_SINGLE,
        self::FORMAT_DOUBLE,
    ];

    const MALE = 1;
    const FEMALE = 2;
    const MIXED = 3;

    const GENDER = [
        self::MALE,
        self::FEMALE,
        self::MIXED,
    ];

    const STATUS_DRAFT = 1;
    const STATUS_OPEN = 2;
    const STATUS_CLOSED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    // Age group constants
    const ALL_AGES = 1;
    const YOUTH = 2; // dưới 18
    const ADULT = 3; // 18-55
    const SENIOR = 4; // trên 55

    const AGE_GROUPS = [
        self::ALL_AGES,
        self::YOUTH,
        self::ADULT,
        self::SENIOR,
    ];

    // Fee settings constants
    const HAS_FEE_FALSE = false;
    const HAS_FEE_TRUE = true;

    const AUTO_SPLIT_FALSE = false;
    const AUTO_SPLIT_TRUE = true;

    // Gender constants
    public function getGenderTextAttribute(): string
    {
        return match($this->attributes['gender'] ?? $this->gender) {
            self::MALE => 'Nam',
            self::FEMALE => 'Nữ',
            self::MIXED => 'Nam nữ',
            default => 'Không xác định',
        };
    }

    public function getPlayModeAttribute($value)
    {
        $map = [
            self::PLAY_MODE_CASUAL => 'casual',
            self::PLAY_MODE_COMPETITION => 'competition',
            self::PLAY_MODE_PRACTICE => 'practice',
        ];
        return $map[$value] ?? $value;
    }

    public function getFormatAttribute($value)
    {
        $map = [
            self::FORMAT_SINGLE => 'single',
            self::FORMAT_DOUBLE => 'double',
            self::FORMAT_MENS_DOUBLES => 'mens_doubles',
            self::FORMAT_WOMENS_DOUBLES => 'womens_doubles',
            self::FORMAT_MIXED => 'mixed',
        ];
        return $map[$value] ?? $value;
    }

    public function getPlayModeTextAttribute(): string
    {
        return match($this->play_mode) {
            self::PLAY_MODE_CASUAL => 'Vui vẻ',
            self::PLAY_MODE_COMPETITION => 'Thi đấu',
            self::PLAY_MODE_PRACTICE => 'Luyện tập',
            default => 'Chưa xác định',
        };
    }

    public function getFormatTextAttribute(): string
    {
        return match($this->format) {
            self::FORMAT_SINGLE => 'Đánh đơn',
            self::FORMAT_DOUBLE => 'Đánh đôi',
            self::FORMAT_MENS_DOUBLES => 'Đánh đôi nam',
            self::FORMAT_WOMENS_DOUBLES => 'Đánh đôi nữ',
            self::FORMAT_MIXED => 'Đánh đôi nam nữ',
            default => 'Chưa xác định',
        };
    }

    public function getHasFeeTextAttribute(): string
    {
        return $this->has_fee ? 'Có phí' : 'Miễn phí';
    }

    public function getAutoSplitFeeTextAttribute(): string
    {
        return $this->auto_split_fee ? 'Chia tiền tự động' : 'Tiền cố định/người';
    }

    /**
     * Hạn chót được phép hủy kèo (theo cấu hình allow_cancellation + cancellation_duration).
     * - Nếu không cho hủy kèo: trả về null (dùng isCancellationClosed() để kiểm tra).
     * - Nếu có cho hủy và có start_time:
     *   - Nếu có cancellation_duration (phút): deadline = start_time - cancellation_duration.
     *   - Nếu không có cancellation_duration: cho phép hủy tới đúng thời điểm start_time.
     */
    public function getCancellationDeadlineAttribute(): ?Carbon
    {
        if (!$this->allow_cancellation || !$this->start_time) {
            return null;
        }

        $startTime = $this->start_time instanceof Carbon
            ? $this->start_time
            : Carbon::parse($this->start_time);

        if ($this->cancellation_duration === null) {
            return $startTime;
        }

        return $startTime->copy()->subMinutes($this->cancellation_duration);
    }

    /**
     * Kiểm tra hiện tại đã hết hạn hủy kèo chưa.
     * - Nếu không cho hủy kèo: luôn coi như đã hết hạn (để API chặn huỷ).
     * - Nếu có deadline: hết hạn khi now > deadline.
     */
    public function isCancellationClosed(?CarbonInterface $now = null): bool
    {
        $now = $now ?? Carbon::now();

        // Nếu cấu hình không cho hủy thì coi như luôn đóng
        if (!$this->allow_cancellation) {
            return true;
        }

        $deadline = $this->cancellation_deadline;

        // Nếu không có deadline cụ thể (thiếu start_time), coi như không đóng
        if ($deadline === null) {
            return false;
        }

        return $now->gt($deadline);
    }

    /**
     * Tính phí mỗi người dựa trên cài đặt
     * Nếu auto_split_fee = true: fee_amount / số người tham gia thực tế
     * Nếu auto_split_fee = false: fee_amount (tiền cố định mỗi người)
     * Nếu kèo đã kết thúc: dùng final_fee_per_person (đã lock)
     */
    public function getFeePerPersonAttribute()
    {
        if (!$this->has_fee) {
            return 0;
        }

        // Nếu kèo đã kết thúc và có final_fee_per_person, dùng giá trị đó
        if ($this->final_fee_per_person !== null) {
            return $this->final_fee_per_person;
        }

        if ($this->auto_split_fee) {
            $participantCount = $this->participants()->count();
            if ($participantCount > 0) {
                return round($this->fee_amount / $participantCount);
            }
            return null;
        }

        return $this->fee_amount;
    }

    /**
     * Tính tổng tiền thu được (dự kiến)
     */
    public function getTotalFeeExpectedAttribute()
    {
        if (!$this->has_fee) {
            return 0;
        }

        $participantCount = $this->participants()->count();
        if ($participantCount > 0) {
            return $this->fee_per_person * $participantCount;
        }

        return 0;
    }

    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Nháp',
            self::STATUS_OPEN => 'Mở',
            self::STATUS_CLOSED => 'Đóng',
            self::STATUS_CANCELLED => 'Hủy',
            default => 'Chưa xác định',
        };
    }

    public function participants()
    {
        return $this->hasMany(MiniParticipant::class);
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function competitionLocation()
    {
        return $this->belongsTo(CompetitionLocation::class);
    }

    public function miniTournamentStaffs()
    {
        return $this->hasMany(MiniTournamentStaff::class, 'mini_tournament_id');
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'mini_tournament_staff')
            ->withPivot('role', 'id')
            ->withTimestamps();
    }

    public function matches()
    {
        return $this->hasMany(MiniMatch::class);
    }

    public function payments()
    {
        return $this->hasMany(MiniParticipantPayment::class);
    }

    /**
     * Get recurring schedule for this tournament
     */
    public function recurringSchedule()
    {
        return $this->hasOne(MiniRecurringSchedule::class);
    }

    public function getRecurringScheduleAttribute($value)
    {
        if (!$value) {
            return null;
        }

        $data = is_array($value) ? $value : json_decode($value, true);
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

    public function participantPayments()
    {
        return $this->hasMany(MiniParticipantPayment::class);
    }

    public function pendingPayments()
    {
        return $this->hasMany(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_PENDING);
    }

    public function awaitingConfirmationPayments()
    {
        return $this->hasMany(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_PAID);
    }

    public function confirmedPayments()
    {
        return $this->hasMany(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_CONFIRMED);
    }

    public function fundCollection()
    {
        return $this->belongsTo(\App\Models\Club\ClubFundCollection::class, 'club_fund_collection_id');
    }

    public function getIncludedInClubFundAttribute(): bool
    {
        return (bool) ($this->attributes['included_in_club_fund'] ?? false);
    }

    public function getPaymentSummaryAttribute(): array
    {
        $participantCount = $this->participants()->count();

        return [
            'total_expected' => $this->has_fee ? ($this->auto_split_fee ?
                ($this->fee_amount * $participantCount) :
                ($this->fee_amount * $participantCount)) : 0,
            'total_collected' => $this->confirmedPayments()->sum('amount'),
            'total_pending' => $this->pendingPayments()->count(),
            'total_awaiting_confirmation' => $this->awaitingConfirmationPayments()->count(),
            'participant_count' => $participantCount,
            'paid_participant_count' => $this->confirmedPayments()->count(),
        ];
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'sport',
            'competitionLocation',
            'club.members',
            'recurringSchedule',
            'participants.user.sports.sport',
            'participants.user.sports.scores',
            'participants.guarantor',
            'participants.invitedBy',
            'miniTournamentStaffs.user',
            'staff',
            'matches',
        ]);
    }

    public function loadFullRelations()
    {
        return $this->load([
            'sport',
            'competitionLocation',
            'club',
            'recurringSchedule',
            'participants.user.sports.sport',
            'participants.user.sports.scores',
            'participants.guarantor',
            'miniTournamentStaffs.user',
            'staff',
            'matches',
        ]);
    }

    public function getAllUsersAttribute(): Collection
    {
        return $this->participants->pluck('user')->filter();
    }

    public function hasOrganizer(int $userId): bool
    {
        return $this->staff->contains(
            fn($staff) =>
            (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
        );
    }

    public function scopeFilter($query, $filter)
    {
        return $query
            ->when(
                !empty($filter['sport_id']),
                fn($q) => $q->whereHas(
                    'sport',
                    fn($sq) => $sq->where('id', $filter['sport_id'])
                )
            )
            ->when(
                !empty($filter['location_id']),
                fn($q) => $q->whereHas(
                    'competitionLocation',
                    fn($lq) => $lq->where('location_id', $filter['location_id'])
                )
            )
            ->when(
                !empty($filter['keyword']),
                fn($q) => $q->where(function ($kq) use ($filter) {
                    $kq->where('mini_tournaments.name', 'like', '%' . $filter['keyword'] . '%')
                        ->orWhereHas('competitionLocation', function ($locSub) use ($filter) {
                            $locSub->where('competition_locations.name', 'like', '%' . $filter['keyword'] . '%')
                                ->orWhere('competition_locations.address', 'like', '%' . $filter['keyword'] . '%')
                                ->orWhereHas(
                                    'location',
                                    fn($lq) => $lq->where('locations.name', 'like', '%' . $filter['keyword'] . '%')
                                );
                        });
                })
            )
            ->when(
                !empty($filter['date_from']),
                fn($q) => $q->whereBetween('start_time', [
                    Carbon::parse($filter['date_from'])->startOfDay(),
                    Carbon::parse($filter['date_from'])->endOfDay()
                ])
            )
            ->when(
                !empty($filter['type']) && is_array($filter['type']),
                function ($q) use ($filter) {
                    $q->where(function ($subQuery) use ($filter) {
                        foreach ($filter['type'] as $type) {
                            if ($type === 'single') {
                                $subQuery->orWhere('format', self::FORMAT_SINGLE);
                            } elseif ($type === 'double') {
                                $subQuery->orWhere('format', self::FORMAT_DOUBLE);
                            }
                        }
                    });
                }
            )
            ->when(!empty($filter['rating']), function ($q) use ($filter) {
                $q->where(function ($outer) use ($filter) {
                    foreach ($filter['rating'] as $rating) {
                        $outer->orWhere(function ($sub) use ($rating) {
                            $sub
                                ->where(function ($c) use ($rating) {
                                    $c->whereNull('min_rating')
                                        ->orWhereRaw('CAST(min_rating AS DECIMAL(10,2)) <= ?', [$rating]);
                                })
                                ->where(function ($c) use ($rating) {
                                    $c->whereNull('max_rating')
                                        ->orWhereRaw('CAST(max_rating AS DECIMAL(10,2)) >= ?', [$rating]);
                                });
                        });
                    }
                });
            })
            ->when(
                !empty($filter['fee']) && is_array($filter['fee']),
                function ($q) use ($filter) {
                    $q->where(function ($subQuery) use ($filter) {
                        foreach ($filter['fee'] as $fee) {
                            if ($fee === 'free') {
                                $subQuery->orWhere('has_fee', false);
                            } elseif ($fee === 'paid') {
                                $subQuery->orWhere('has_fee', true);
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filter['time_of_day']) && is_array($filter['time_of_day']),
                function ($q) use ($filter) {
                    $q->where(function ($subQuery) use ($filter) {
                        foreach ($filter['time_of_day'] as $timeOfDay) {
                            if ($timeOfDay === 'morning') {
                                $subQuery->orWhereTime('start_time', '<', '11:00:00');
                            } elseif ($timeOfDay === 'afternoon') {
                                $subQuery->orWhere(function ($timeQuery) {
                                    $timeQuery->whereTime('start_time', '>=', '11:00:00')
                                        ->whereTime('start_time', '<', '16:00:00');
                                });
                            } elseif ($timeOfDay === 'evening') {
                                $subQuery->orWhereTime('start_time', '>=', '16:00:00');
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filter['slot_status']) && is_array($filter['slot_status']),
                function ($q) use ($filter) {
                    $q->where(function ($subQuery) use ($filter) {
                        foreach ($filter['slot_status'] as $slotStatus) {
                            if ($slotStatus === 'one_slot') {
                                $subQuery->orWhereRaw('(
                                COALESCE(max_players, 0) - (
                                    SELECT COUNT(*)
                                    FROM mini_participants
                                    WHERE mini_participants.mini_tournament_id = mini_tournaments.id
                                )
                            ) >= 1');
                            } elseif ($slotStatus === 'two_slot') {
                                $subQuery->orWhereRaw('(
                                COALESCE(max_players, 0) - (
                                    SELECT COUNT(*)
                                    FROM mini_participants
                                    WHERE mini_participants.mini_tournament_id = mini_tournaments.id
                                )
                            ) >= 2');
                            } elseif ($slotStatus === 'full_slot') {
                                $subQuery->orWhereRaw('(
                                SELECT COUNT(*)
                                FROM mini_participants
                                WHERE mini_participants.mini_tournament_id = mini_tournaments.id
                            ) = 0');
                            }
                        }
                    });
                }
            )
            ->when(true, function ($q) {
                $userId = auth()->id();

                $q->where(function ($sub) use ($userId) {
                    // Kèo công khai, không private, không ở trạng thái draft/cancelled/closed
                    $sub->where(function ($publicSub) {
                        $publicSub->where('is_private', '!=', 1)
                            ->whereNotIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_CLOSED, MiniTournament::STATUS_CANCELLED]);
                    });

                    if ($userId) {
                        // Organizer: thấy tất cả kèo mình tổ chức (kể cả draft)
                        $sub->orWhere(function ($staffSub) use ($userId) {
                            $staffSub->whereHas('miniTournamentStaffs', function ($staffQuery) use ($userId) {
                                $staffQuery->where('user_id', $userId)
                                    ->where('role', MiniTournamentStaff::ROLE_ORGANIZER);
                            });
                        });

                        // Participant: thấy tất cả kèo mình tham gia (kể cả draft)
                        $sub->orWhere(function ($partSub) use ($userId) {
                            $partSub->whereHas('participants', function ($partQuery) use ($userId) {
                                $partQuery->where('user_id', $userId);
                            });
                        });
                    }
                });
            });
    }

    public function scopeNearBy($query, $lat, $lng, $radiusKm)
    {
        $haversine = "(6371 * acos(
            cos(radians(?))
            * cos(radians(competition_locations.latitude))
            * cos(radians(competition_locations.longitude) - radians(?))
            + sin(radians(?))
            * sin(radians(competition_locations.latitude))
        ))";

        return $query->whereHas('competitionLocation', function ($q) use ($haversine, $lat, $lng, $radiusKm) {
            $q->whereRaw("$haversine < ?", [
                $lat,
                $lng,
                $lat,
                $radiusKm
            ]);
        });
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereHas('competitionLocation', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
            $q->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng]);
        });
    }

    public function scopeOrderByDistanceFromLocation(
        Builder $query,
        float $lat,
        float $lng
    ) {
        return $query
            ->leftJoin('competition_locations', 'competition_locations.id', '=', 'mini_tournaments.competition_location_id')
            ->select('mini_tournaments.*')
            ->selectRaw("
                (
                    6371 * acos(
                        cos(radians(?))
                        * cos(radians(competition_locations.latitude))
                        * cos(radians(competition_locations.longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(competition_locations.latitude))
                    )
                ) AS distance
            ", [$lat, $lng, $lat])
            ->orderByRaw('competition_locations.latitude IS NULL OR competition_locations.longitude IS NULL')
            ->orderBy('distance', 'asc');
    }

    public function isRecurring(): bool
    {
        return $this->recurring_schedule !== null && !empty($this->recurring_schedule);
    }

    public function isRecurrenceSeriesCancelled(): bool
    {
        return $this->recurrence_series_cancelled_at !== null;
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

    private function parseDate(string $dateString): ?array
    {
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd-m-Y H:i:s'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date) {
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

    public function getRecurringDateParts(): ?array
    {
        $schedule = $this->getRecurringScheduleRaw();
        if (!$schedule || empty($schedule['recurring_date'])) {
            return null;
        }
        return $this->parseDate($schedule['recurring_date']);
    }

    public function calculateNextOccurrence(?Carbon $fromDate = null): ?Carbon
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
        $timeString = $this->start_time ? (is_string($this->start_time) ? Carbon::parse($this->start_time)->format('H:i:s') : $this->start_time->format('H:i:s')) : $fromDate->format('H:i:s');

        foreach ($weekDays as $targetDay) {
            if ($targetDay > $currentDayOfWeek) {
                $daysToAdd = $targetDay - $currentDayOfWeek;
                return $fromDate->copy()->addDays($daysToAdd)->setTimeFromTimeString($timeString);
            }
        }

        $daysToAdd = 7 - $currentDayOfWeek + $weekDays[0];
        return $fromDate->copy()->addDays($daysToAdd)->setTimeFromTimeString($timeString);
    }

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
            $timeString = is_string($this->start_time) ? Carbon::parse($this->start_time)->format('H:i:s') : $this->start_time->format('H:i:s');
            $nextDate->setTimeFromTimeString($timeString);
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

        $timeString = $this->start_time ? (is_string($this->start_time) ? Carbon::parse($this->start_time)->format('H:i:s') : $this->start_time->format('H:i:s')) : null;
        $currentYear = $fromDate->year;

        foreach ([$currentYear, $currentYear + 1] as $year) {
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
            $timeString = is_string($this->start_time) ? Carbon::parse($this->start_time)->format('H:i:s') : $this->start_time->format('H:i:s');
            $nextDate->setTimeFromTimeString($timeString);
        }

        if ($nextDate->lte($fromDate)) {
            $nextDate->addYear();
        }

        return $nextDate;
    }
}
