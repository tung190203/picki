<?php

namespace App\Models;

use App\Models\Club\Club;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'poster',
        'name',
        'sport_id',
        'start_date',
        'registration_open_at',
        'registration_closed_at',
        'early_registration_deadline',
        'duration',
        'enable_dupr',
        'enable_vndupr',
        'min_level',
        'max_level',
        'age_group',
        'gender_policy',
        'participant',
        'max_team',
        'player_per_team',
        'max_player',
        'is_private',
        'auto_approve',
        'end_date',
        'competition_location_id',
        'club_id',
        'created_by',
        'description',
        'status',
        'is_public_branch',
        'is_own_score',
        'creator_join',
        'has_financial_management',
        'has_fee',
        'fee_amount',
        'auto_split_fee',
        'fee_description',
        'qr_code_url',
        'tournament_fund_collection_id',
        'final_fee_per_person',
        'auto_payment_created',
        'cancelled_reason',
    ];

    protected $casts = [
        'has_financial_management' => 'bool',
        'has_fee' => 'bool',
        'fee_amount' => 'integer',
        'auto_split_fee' => 'bool',
        'final_fee_per_person' => 'integer',
        'auto_payment_created' => 'bool',
    ];

    protected $appends = ['poster_url', 'qr_code_url'];
    protected $hidden = ['poster'];

    const PER_PAGE = 10;

    const ALL_AGES = 1;
    const YOUTH = 2; // dưới 18
    const ADULT = 3; // từ 18 - 55
    const  SENIOR = 4; // trên 55

    const AGES = [
        self::ALL_AGES,
        self::YOUTH,
        self::ADULT,
        self::SENIOR,
    ];

    const MALE = 1;
    const FEMALE = 2;
    const MIXED = 3;

    const GENDER = [
        self::MALE,
        self::FEMALE,
        self::MIXED,
    ];

    const DRAFT = 1;
    const OPEN = 2;
    const CLOSED = 3;
    const CANCELLED = 4;

    const STATUS = [
        self::DRAFT,
        self::OPEN,
        self::CLOSED,
        self::CANCELLED,
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tournamentTypes()
    {
        return $this->hasMany(TournamentType::class, 'tournament_id');
    }

    public function groups()
    {
        return $this->hasManyThrough(Group::class, TournamentType::class);
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'tournament_id');
    }

    public function matches()
    {
        return $this->hasManyThrough(Matches::class, Group::class);
    }

    Public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }

    public function tournamentStaffs()
    {
        return $this->hasMany(TournamentStaff::class, 'tournament_id');
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'tournament_staff')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function competitionLocation()
    {
        return $this->belongsTo(CompetitionLocation::class);
    }

    public function fundCollection()
    {
        return $this->belongsTo(TournamentFundCollection::class, 'tournament_fund_collection_id');
    }

    public function clubFundCollection()
    {
        return $this->belongsTo(\App\Models\Club\ClubFundCollection::class, 'club_fund_collection_id');
    }

    public function payments()
    {
        return $this->hasMany(TournamentParticipantPayment::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class, 'tournament_id');
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'createdBy',
            'club' => fn($q) => $q->withCount('members'),
            'sport',
            'tournamentTypes.groups.matches.homeTeam',
            'tournamentTypes.groups.matches.homeTeam.members',
            'tournamentTypes.groups.matches.awayTeam',
            'tournamentTypes.groups.matches.awayTeam.members',
            'tournamentStaffs',
            'tournamentStaffs.user.sports.scores',
            'participants',
            'participants.user.sports.scores',
            'participants.guarantor',
            'competitionLocation'
        ]);
    }

    public function scopeWithBasicRelations($query)
    {
        return $query->with([
            'createdBy',
            'club' => fn($q) => $q->withCount('members'),
            'sport',
            'tournamentStaffs',
            'competitionLocation'
        ]);
    }
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }
    public function scopeOngoing($query)
    {
        return $query->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
    public function scopeFinished($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeStarted($query)
    {
        return $query->where('start_date', '<=', now());
    }

    public function scopeSearch($query, $keyword)
    {
        return $query->where('name', 'like', '%' . $keyword . '%');
    }

    public function scopeFilterByDate($query, $startDate = null, $endDate = null)
    {
        return $query
            ->when($startDate, fn($q) => $q->whereDate('start_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('end_date', '<=', $endDate));
    }

    public function scopeApplyTimeline($query, ?string $timeFilter, ?int $userId = null)
    {
        if (!$timeFilter || $timeFilter === 'all') {
            return $query;
        }

        $userId = $userId ?? auth()->id();

        return match ($timeFilter) {
            'mine' => $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('participants', fn($p) => $p->where('user_id', $userId));
            }),
            'today' => $query->whereDate('start_date', now()->toDateString()),
            'this_week' => $query->whereBetween('start_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ]),
            'this_month' => $query->whereBetween('start_date', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]),
            default => $query,
        };
    }

    public function getAgeGroupTextAttribute()
    {
        return match ($this->age_group) {
            self::ALL_AGES => 'Mọi lứa tuổi',
            self::YOUTH => 'Thiếu niên (dưới 18)',
            self::ADULT => 'Người lớn (18-55)',
            self::SENIOR => 'Cao tuổi (trên 55)',
            default => 'Không xác định',
        };
    }

    public function getGenderPolicyTextAttribute()
    {
        return match ($this->gender_policy) {
            self::MIXED => 'Nam Nữ',
            self::MALE => 'Nam',
            self::FEMALE => 'Nữ',
            default => 'Không xác định',
        };
    }

    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            self::DRAFT => 'Bản nháp',
            self::OPEN => 'Mở đăng ký',
            self::CLOSED => 'Đóng đăng ký',
            self::CANCELLED => 'Hủy',
            default => 'Không xác định',
        };
    }

    public function getPosterUrlAttribute()
    {
        if (!array_key_exists('poster', $this->attributes) || !$this->attributes['poster']) {
            return null;
        }
        return asset('storage/' . $this->attributes['poster']);
    }

    public function getQrCodeUrlAttribute()
    {
        if (!array_key_exists('qr_code_url', $this->attributes) || !$this->attributes['qr_code_url']) {
            return null;
        }
        $url = $this->attributes['qr_code_url'];
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return asset('storage/' . ltrim($url, '/'));
    }

    public function getAllUsersAttribute(): Collection
    {
        $directUsers = $this->participants
            ->map(fn($p) => $p->user)
            ->filter();
        $staffUsers = $this->staff ?? collect();

        return $directUsers->merge($staffUsers)->unique('id')->values();
    }

    public function hasOrganizer(int $userId): bool
    {
        return $this->staff->contains(
            fn($staff) =>
            (int) $staff->pivot->user_id === $userId
            && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
        );
    }

    public function hasOrganizerOrStaff(int $userId): bool
    {
        return $this->staff->contains(
            fn($staff) =>
            (int) $staff->pivot->user_id === $userId
            && in_array((int) $staff->pivot->role, [TournamentStaff::ROLE_ORGANIZER, TournamentStaff::ROLE_STAFF])
        );
    }

    public function hasScoringPermission(int $userId): bool
    {
        return $this->staff->contains(
            fn($staff) =>
            (int) $staff->pivot->user_id === $userId
            && in_array((int) $staff->pivot->role, [TournamentStaff::ROLE_ORGANIZER, TournamentStaff::ROLE_STAFF, TournamentStaff::ROLE_REFEREE])
        );
    }

    public function getHasFeeAttribute(): bool
    {
        return (bool) ($this->attributes['has_fee'] ?? false);
    }

    /**
     * Tính phí mỗi người dựa trên cài đặt.
     * Nếu auto_split_fee = true: fee_amount / số người tham gia
     * Nếu auto_split_fee = false: fee_amount (tiền cố định mỗi người)
     */
    public function getFeePerPersonAttribute()
    {
        if (!$this->has_fee) {
            return 0;
        }

        if ($this->auto_split_fee) {
            if ($this->final_fee_per_person !== null) {
                return (int) $this->final_fee_per_person;
            }
            $participantCount = $this->participants()->where('is_confirmed', true)->count();
            if ($participantCount > 0) {
                return (int) round($this->fee_amount / $participantCount);
            }
            return 0;
        }

        return (int) ($this->fee_amount ?? 0);
    }

    /**
     * Tính tổng tiền dự kiến.
     */
    public function getTotalFeeExpectedAttribute(): int
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

    public function getPaymentSummaryAttribute(): array
    {
        $participantCount = $this->participants()->count();

        $totalExpected = 0;
        if ($this->has_fee) {
            if ($this->auto_split_fee) {
                // auto_split=true: tổng tiền cố định = fee_amount
                $totalExpected = (int) ($this->fee_amount ?? 0);
            } else {
                // auto_split=false: mỗi người đóng fee_amount
                $totalExpected = (int) ($this->fee_amount ?? 0) * $participantCount;
            }
        }

        return [
            'total_expected' => $totalExpected,
            'total_collected' => $this->payments()->where('status', TournamentParticipantPayment::STATUS_CONFIRMED)->sum('amount'),
            'total_pending' => $this->payments()->whereIn('status', [TournamentParticipantPayment::STATUS_PENDING, TournamentParticipantPayment::STATUS_PAID])->count(),
            'participant_count' => $participantCount,
            'paid_participant_count' => $this->payments()->where('status', TournamentParticipantPayment::STATUS_CONFIRMED)->count(),
        ];
    }

    /**
     * Kiểm tra user có quyền check-in / đánh dấu vắng (chỉ host và staff, không có referee).
     */
    public function hasAttendancePermission(int $userId): bool
    {
        return $this->staff->contains(
            fn($staff) =>
            (int) $staff->pivot->user_id === $userId
            && in_array((int) $staff->pivot->role, [TournamentStaff::ROLE_ORGANIZER, TournamentStaff::ROLE_STAFF])
        );
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when(
                !empty($filters['sport_id']),
                fn($q) => $q->whereHas(
                    'sport',
                    fn($sq) => $sq->where('id', $filters['sport_id'])
                )
            )
            ->when(
                !empty($filters['competition_location_id']),
                fn($q) => $q->where('competition_location_id', $filters['competition_location_id'])
            )
            ->when(
                !empty($filters['keyword']),
                fn($q) => $q->where(function ($kq) use ($filters){
                    $kq->where('tournaments.name', 'like', '%' . $filters['keyword'] . '%')
                        ->orWhereHas('competitionLocation', function ($clq) use ($filters) {
                            $clq->where('competition_locations.name', 'like', '%' . $filters['keyword'] . '%')
                                ->orWhere('competition_locations.address', 'like', '%' . $filters['keyword'] . '%');
                        });
                })
            )
            ->when(
                !empty($filters['date_from']),
                fn($q) => $q->whereBetween('start_date', [
                    Carbon::parse($filters['date_from'])->startOfDay(),
                    Carbon::parse($filters['date_from'])->endOfDay(),
                ])
            )
            ->when(!empty($filters['rating']), function ($q) use ($filters) {
                $minRating = (int) min($filters['rating']);
                $maxRating = (int) max($filters['rating']);

                $q->where(function ($rq) use ($minRating, $maxRating) {
                    $rq->where(function ($c) use ($minRating) {
                        $c->whereNull('max_level')
                          ->orWhere('max_level', '>=', $minRating);
                    })
                    ->where(function ($c) use ($maxRating) {
                        $c->whereNull('min_level')
                          ->orWhere('min_level', '<=', $maxRating);
                    });
                });
            })
            ->when(
                !empty($filters['fee']) && is_array($filters['fee']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters){
                        foreach($filters['fee'] as $fee){
                            if($fee === 'free') {
                                $subQuery->orWhere('has_fee', false);
                            } elseif($fee === 'paid') {
                                $min = $filters['min_price'] ?? 0;
                                $max = $filters['max_price'] ?? PHP_INT_MAX;

                                $subQuery->orWhere(function ($paid) use ($min, $max){
                                    $paid->where('has_fee', true)
                                         ->whereBetween('fee_amount', [$min, $max]);
                                });
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filters['time_of_day']) && is_array($filters['time_of_day']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters){
                        foreach($filters['time_of_day'] as $timeOfDay){
                            if($timeOfDay === 'morning') {
                                $subQuery->orWhereTime('start_date', '<', '11:00:00');
                            } elseif($timeOfDay === 'afternoon') {
                               $subQuery->orWhere(function ($timeQuery){
                                $timeQuery->whereTime('start_date', '>=', '11:00:00')
                                          ->whereTime('start_date', '<', '16:00:00');
                               });
                            } elseif($timeOfDay === 'evening') {
                                $subQuery->orWhereTime('start_date', '>=', '16:00:00');
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filters['slot_status']) && is_array($filters['slot_status']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters) {
                        foreach($filters['slot_status'] as $slotStatus){
                            if($slotStatus === 'con_trong') {
                                $subQuery->orWhereRaw('(
                                    COALESCE(max_player, 0) - (
                                        SELECT COUNT(*)
                                        FROM participants
                                        WHERE participants.tournament_id = tournaments.id
                                    )
                                ) > 0');
                            } elseif($slotStatus === 'da_day') {
                                $subQuery->orWhereRaw('(
                                    COALESCE(max_player, 0) - (
                                        SELECT COUNT(*)
                                        FROM participants
                                        WHERE participants.tournament_id = tournaments.id
                                    )
                                ) <= 0');
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filters['club_type']) && is_array($filters['club_type']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters) {
                        foreach ($filters['club_type'] as $clubType) {
                            if ($clubType === 'thuong') {
                                $subQuery->orWhereNull('club_id');
                            } elseif ($clubType === 'clb') {
                                $subQuery->orWhereNotNull('club_id');
                            }
                        }
                    });
                }
            )
            ->when(true, function ($q){
                $userId = auth()->id();

                $q->where(function ($sub) use ($userId) {
                    $sub->where('is_private', '!=', self::DRAFT)
                        ->whereNotIn('status', [self::DRAFT, self::CLOSED, self::CANCELLED]);

                    if($userId) {
                        $sub->orWhere(function ($visible) use ($userId){
                            $visible->orWhereHas('tournamentStaffs', function ($staffQuery) use ($userId){
                                $staffQuery->where('user_id', $userId)
                                    ->where('role', TournamentStaff::ROLE_ORGANIZER);
                            })
                            ->orWhereHas('participants', function ($participantQuery) use ($userId){
                                $participantQuery->where('user_id', $userId);
                            });
                        });
                    }
                });
            });
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereHas('competitionLocation', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
            $q->whereBetween('latitude', [$minLat, $maxLat])
              ->whereBetween('longitude', [$minLng, $maxLng]);
        });
    }
    public function scopeNearBy($query, $lat, $lng, float $radiusMeters)
    {
        $radiusKm = $radiusMeters / 1000;

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
    public function scopeOrderByDistanceFromLocation(
        Builder $query,
        float $lat,
        float $lng
    ) {
        return $query
            ->leftJoin('competition_locations', 'competition_locations.id', '=', 'tournaments.competition_location_id')
            ->select('tournaments.*')
            ->selectRaw("
                (
                    6371000 * acos(
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

    /**
     * Kiểm tra tournament có trận đấu đã lưu/xác nhận kết quả hay không.
     * @return bool
     */
    public function hasMatchesWithResults(): bool
    {
        return $this->tournamentTypes()
            ->whereHas('groups.matches', function ($q) {
                $q->whereHas('results');
            })
            ->exists();
    }

    /**
     * Kiểm tra tournament có trận chung kết đã xác nhận kết quả hay chưa.
     * Round = 4 là Chung kết (theo TournamentType::FORMAT_ELIMINATION).
     * @return bool
     */
    public function hasFinalMatchWithResult(): bool
    {
        return $this->tournamentTypes()
            ->where('format', TournamentType::FORMAT_ELIMINATION)
            ->whereHas('groups.matches', function ($q) {
                $q->where('round', 4)
                  ->whereHas('results');
            })
            ->exists();
    }
}
