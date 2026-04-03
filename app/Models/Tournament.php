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
        'fee',
        'standard_fee_amount',
        'is_private',
        'auto_approve',
        'end_date',
        'competition_location_id',
        'club_id',
        'created_by',
        'description',
        'status',
        'is_public_branch',
        'is_own_score'
    ];

    protected $appends = ['poster_url'];

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

    public function teams()
    {
        return $this->hasMany(Team::class, 'tournament_id');
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'createdBy',
            'club',
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
        return $query->with(['createdBy', 'club', 'sport', 'tournamentStaffs', 'competitionLocation'] );
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
        return $this->poster ? asset('storage/' . $this->poster) : null;
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
                !empty($filters['location_id']),
                fn($q) => $q->whereHas(
                    'competitionLocation',
                    fn($lq) => $lq->where('id', $filters['location_id'])
                )
            )
            ->when(
                !empty($filters['keyword']),
                fn($q) => $q->where(function ($kq) use ($filters){
                    $kq->where('tournaments.name', 'like', '%' . $filters['keyword'] . '%')
                    ->orWhereHas('competitionLocation', function ($clq) use ($filters) {
                        $clq->where('competition_locations.name', 'like', '%' . $filters['keyword'] . '%')
                            ->orWhere('competition_locations.address', 'like', '%' . $filters['keyword'] . '%')
                            ->orWhereHas(
                                'location',
                                fn($llq) => $llq->where('locations.name', 'like', '%' . $filters['keyword'] . '%')
                            );
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
                                $subQuery->orWhere('fee', 'free');
                            } elseif($fee === 'paid') {
                                $min = $filters['min_price'] ?? 0;
                                $max = $filters['max_price'] ?? PHP_INT_MAX;

                                $subQuery->orWhere(function ($paid) use ($min, $max){
                                    $paid->where('fee', 'paid')
                                         ->whereBetween('standard_fee_amount', [$min, $max]);
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
                            if($slotStatus === 'one_slot') {
                                $subQuery->orWhereRaw('(
                                    COALESCE(max_player, 0) - (
                                        SELECT COUNT(*)
                                        FROM participants
                                        WHERE participants.tournament_id = tournaments.id
                                    )
                                ) >= 1');
                            } elseif($slotStatus === 'two_slot') {
                                $subQuery->orWhereRaw('(
                                    COALESCE(max_player, 0) - (
                                        SELECT COUNT(*)
                                        FROM participants
                                        WHERE participants.tournament_id = tournaments.id
                                    )
                                ) >= 2');
                            } elseif($slotStatus === 'full_slot') {
                                $subQuery->orWhereRaw('(
                                    COALESCE(max_player, 0) - (
                                        SELECT COUNT(*)
                                        FROM participants
                                        WHERE participants.tournament_id = tournaments.id
                                    )
                                ) = 0');
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
    public function scopeNearBy($query, $lat, $lng, $radius)
    {
        $haversine = "(6371 * acos(
            cos(radians(?))
            * cos(radians(competition_locations.latitude))
            * cos(radians(competition_locations.longitude) - radians(?))
            + sin(radians(?))
            * sin(radians(competition_locations.latitude))
        ))";

        return $query->whereHas('competitionLocation', function ($q) use ($haversine, $lat, $lng, $radius) {
            $q->whereRaw("$haversine < ?", [
                $lat,
                $lng,
                $lat,
                $radius
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
}
