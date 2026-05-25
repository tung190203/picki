<?php

namespace App\Models;

use App\Enums\ClubMembershipStatus;
use App\Models\SuperAdminDraft;
use App\Models\Club\Club;
use App\Models\QuickMatch;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'avatar_url',
        'thumbnail',
        'latest_used_qr',
        'location_id',
        'about',
        'google_id',
        'facebook_id',
        'role',
        'email_verified_at',
        'is_profile_completed',
        'gender',
        'date_of_birth',
        'latitude',
        'longitude',
        'address',
        'last_login',
        'visibility',
        'phone',
        'self_score',
        'apple_id',
        'total_matches',
        'is_guest',
        'last_active_at',
        'is_super_admin',
        'is_verified',
        'is_anchor',
        'is_banned',
        'banned_at',
        'ban_reason',
        'banned_by',
        'ban_note',
        'trust_score',
    ];

    const PER_PAGE = 15;

    const PLAYER = 'player';
    const ADMIN = 'admin';
    const REFEREE = 'referee';

    const ROLE = [
        self::PLAYER,
        self::ADMIN,
        self::REFEREE,
    ];

    const MALE = 1;
    const FEMALE = 2;

    const OTHER = 0;

    const NO_PUBLIC = 3;

    const GENDER = [
        self::MALE,
        self::FEMALE,
        self::OTHER,
        self::NO_PUBLIC
    ];

    const MORNING = 'morning';
    const AFTERNOON = 'afternoon';
    const EVENING = 'evening';
    const PLAY_TIME_OPTIONS = [
        self::MORNING,
        self::AFTERNOON,
        self::EVENING
    ];

    const VISIBILITY_PUBLIC = 'open';
    const VISIBILITY_FRIEND_ONLY = 'friend-only';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_OPTIONS = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_FRIEND_ONLY,
        self::VISIBILITY_PRIVATE
    ];

    const LOW_RATING = 'low';
    const MEDIUM_RATING = 'medium';
    const HIGH_RATING = 'high';

    const RECENT_MATCHES_OPTIONS = [
        self::LOW_RATING,
        self::MEDIUM_RATING,
        self::HIGH_RATING
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'age_years',
        'age_group',
        'gender_text',
        'is_super_admin',
        'is_online',
    ];

    public function getGenderText()
    {
        return match ($this->gender) {
            self::MALE => 'Nam',
            self::FEMALE => 'Nữ',
            self::NO_PUBLIC => 'Không tiết lộ',
            default => 'Khác',
        };
    }

    public function getGenderTextAttribute()
    {
        return $this->getGenderText();
    }

    public function getAgeYearsAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        try {
            return Carbon::parse($this->date_of_birth)->age;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getAgeGroupAttribute(): ?string
    {
        $age = $this->age_years;

        if ($age === null) {
            return null;
        }

        if ($age < 10) {
            return 'Trẻ em';
        }

        if ($age >= 10 && $age <= 15) {
            return 'Thiếu niên nhỏ';
        }

        if ($age >= 16 && $age <= 17) {
            return 'Vị thành niên';
        }

        if ($age >= 18) {
            return 'Người lớn';
        }

        return null;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'last_active_at' => 'datetime',
        'password' => 'hashed',
        'is_guest' => 'boolean',
        'is_super_admin' => 'boolean',
        'is_verified' => 'boolean',
        'is_anchor' => 'boolean',
        'is_banned' => 'boolean',
        'banned_at' => 'datetime',
        'trust_score' => 'float',
    ];
    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function getAvatarUrlAttribute($value): ?string
    {
        return $value;
    }

    public function isOnline(int $minutesThreshold = 15): bool
    {
        if (!$this->last_login) {
            return false;
        }
        return $this->last_login->diffInMinutes(now()) <= $minutesThreshold;
    }

    public function follows()
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    public function followings()
    {
        return $this->hasMany(Follow::class, 'user_id');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'is_super_admin' => (bool) $this->is_super_admin,
            'is_verified' => (bool) $this->is_verified,
        ];
    }
    public function referee()
    {
        return $this->hasOne(Referee::class);
    }

    public function playTimes()
    {
        return $this->hasMany(UserPlayTime::class);
    }

    public function superAdminDraft()
    {
        return $this->hasOne(SuperAdminDraft::class, 'user_id');
    }

    public function getIsSuperAdminAttribute()
    {
        return (bool) ($this->attributes['is_super_admin'] ?? false);
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withTimestamps();
    }

    public function sport()
    {
        return $this->belongsToMany(Sport::class, 'user_sport')
            ->withPivot('tier')
            ->withTimestamps();
    }

    public function sports()
    {
        return $this->hasMany(UserSport::class);
    }

    public function vnduprScores()
    {
        return $this->hasManyThrough(
            UserSportScore::class,
            UserSport::class,
            'user_id',
            'user_sport_id',
            'id',
            'id'
        )->where('score_type', 'vndupr_score');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'club_members')
            ->wherePivot('membership_status', 'joined')
            ->wherePivot('status', 'active')
            ->withPivot(['is_manager', 'joined_at', 'membership_status', 'status'])
            ->withTimestamps();
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'user_id');
    }

    public function miniParticipants()
    {
        return $this->hasMany(MiniParticipant::class, 'user_id');
    }

    public function matches()
    {
        return $this->hasManyThrough(
            Matches::class,
            Participant::class,
            'user_id',
            'participant1_id',
            'id',
            'id'
        )->orWhereHas('participant2', fn($q) => $q->where('user_id', $this->id));
    }

    public function miniMatches()
    {
        return $this->hasManyThrough(
            MiniMatch::class,
            MiniParticipant::class,
            'user_id',
            'participant1_id',
            'id',
            'id'
        )->orWhereHas('participant2', fn($q) => $q->where('user_id', $this->id));
    }

    public function messagesAsSender()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messagesAsReceiver()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public const FULL_RELATIONS = ['referee', 'follows', 'playTimes', 'sports', 'sports.sport', 'sports.scores', 'clubs'];

    public function scopeWithFullRelations($query, ?int $sportId = null)
    {
        $query = $query->with(['referee', 'follows', 'playTimes', 'sports', 'sports.sport', 'sports.scores', 'clubs.members']);

        // Apply pickleball stats for vn_rank, defaulting to sport_id = 1 if not specified
        $effectiveSportId = $sportId ?? 1;
        $query->withPickleballStats($effectiveSportId);

        return $query;
    }

    public function scopeLoadFullRelations()
    {
        return $this->load(self::FULL_RELATIONS);
    }

    /**
     * Scope: Lọc chỉ guest accounts (is_guest = true)
     */
    public function scopeGuests($query)
    {
        return $query->where('is_guest', true);
    }

    /**
     * Scope: Lọc guest không hoạt động sau N ngày
     * @param int $days Số ngày không hoạt động
     */
    public function scopeInactiveGuests($query, int $days = 7)
    {
        return $query->guests()
            ->where(function ($q) use ($days) {
                $q->whereNull('last_active_at')
                    ->orWhere('last_active_at', '<', now()->subDays($days));
            });
    }

    public function isMutualWith(User $other): bool
    {
        return $this->followings()
            ->where('followable_type', User::class)
            ->where('followable_id', $other->id)
            ->exists()
            &&
            $other->followings()
                ->where('followable_type', User::class)
                ->where('followable_id', $this->id)
                ->exists();
    }

    public function scopeVisibleFor($query, User $currentUser)
    {
        return $query->where(function ($q) use ($currentUser) {
            $q->where('visibility', 'open');

            $q->orWhere(function ($q2) use ($currentUser) {
                $q2->where('visibility', 'friend-only')
                    ->whereExists(function ($sub) use ($currentUser) {
                        $sub->select(DB::raw(1))
                            ->from('follows as f1')
                            ->join('follows as f2', function ($join) {
                                $join->on('f1.user_id', '=', 'f2.followable_id')
                                    ->on('f1.followable_id', '=', 'f2.user_id')
                                    ->where('f1.followable_type', User::class)
                                    ->where('f2.followable_type', User::class);
                            })
                            ->where('f1.user_id', $currentUser->id)
                            ->whereColumn('f1.followable_id', 'users.id');
                    });
            });
        });
    }

    public function scopeWithPickleballStats($query, $sportId)
    {
        if (!$sportId) return $query;

        $scoreSubquery = DB::table('user_sport')
            ->join('user_sport_scores', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->whereColumn('user_sport.user_id', 'users.id')
            ->select(DB::raw('MAX(user_sport_scores.score_value)'));

        $rankSubquery = DB::table('users as u2')
            ->join('user_sport as us2', 'u2.id', '=', 'us2.user_id')
            ->join('user_sport_scores as uss2', 'us2.id', '=', 'uss2.user_sport_id')
            ->where('u2.total_matches', '>', 5)
            ->where('us2.sport_id', $sportId)
            ->where('uss2.score_type', 'vndupr_score')
            ->where('uss2.score_value', '>', DB::raw('(' . $scoreSubquery->toSql() . ')'))
            ->mergeBindings($scoreSubquery)
            ->select(DB::raw('COUNT(DISTINCT u2.id) + 1'));

        return $query->addSelect([
            'vn_rank' => $rankSubquery
        ]);
    }

    public function scopeWithInteractionStatus($query, $currentUserId)
    {
        if (!$currentUserId) return $query;

        $isFollowingSubquery = DB::table('follows')
            ->where('user_id', $currentUserId)
            ->where('followable_type', User::class)
            ->whereColumn('followable_id', 'users.id')
            ->select(DB::raw(1));

        $isFollowedBySubquery = DB::table('follows')
            ->where('followable_id', $currentUserId)
            ->where('followable_type', User::class)
            ->whereColumn('user_id', 'users.id')
            ->select(DB::raw(1));

        return $query->addSelect([
            'is_following_count' => $isFollowingSubquery,
            'is_followed_by_count' => $isFollowedBySubquery,
        ]);
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);
    }

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when(
                !empty($filters['keyword']),
                fn($query) => $query->where(function ($q) use ($filters) {
                    $q->where('full_name', 'like', '%' . $filters['keyword'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['keyword'] . '%');
                })
            )
            ->when(
                !empty($filters['sport_id']),
                fn($query) => $query->whereHas('sports', function ($q) use ($filters) {
                    $q->where('sport_id', $filters['sport_id']);
                })
            )
            ->when(
                !empty($filters['location_id']),
                fn($query) => $query->where('location_id', $filters['location_id'])
            )
            ->when(
                !empty($filters['favourite_player']) && $filters['favourite_player'] == true,
                fn($query) => $query->whereHas('follows', function ($q) {
                    $q->where('user_id', auth()->id())
                        ->where('followable_type', User::class);
                })
            )
            ->when(
                !empty($filters['is_connected']) && $filters['is_connected'] == true,
                fn($query) => $query->where(function ($q) {
                    $q->whereHas('messagesAsSender', function ($q2) {
                        $q2->where('receiver_id', auth()->id());
                    })->orWhereHas('messagesAsReceiver', function ($q2) {
                        $q2->where('sender_id', auth()->id());
                    });
                })
            )
            ->when(
                isset($filters['gender']),
                fn ($query) => $query->where('gender', $filters['gender'])
            )
            ->when(
                !empty($filters['time_of_day']) && is_array($filters['time_of_day']),
                fn($query) => $query->whereHas('playTimes', function ($q) use ($filters) {
                    $timeOfDayArray = $filters['time_of_day'];

                    $q->where(function ($query) use ($timeOfDayArray) {
                        foreach ($timeOfDayArray as $timeOfDay) {
                            if ($timeOfDay === 'morning') {
                                $query->orWhereTime('start_time', '<', '11:00:00');
                            } elseif ($timeOfDay === 'afternoon') {
                                $query->orWhere(function ($subQuery) {
                                    $subQuery->whereTime('start_time', '>=', '11:00:00')
                                        ->whereTime('start_time', '<=', '16:00:00');
                                });
                            } elseif ($timeOfDay === 'evening') {
                                $query->orWhereTime('start_time', '>', '16:00:00');
                            }
                        }
                    });
                })
            )
            ->when(
                !empty($filters['rating']) && is_array($filters['rating']),
                function ($query) use ($filters) {
                    $ratings = array_map('floatval', $filters['rating']);
                    $query->whereHas('sports.scores', function ($q) use ($ratings) {
                        $q->whereNotIn('score_type', ['personal_score'])
                            ->whereIn('score_value', function ($subQuery) use ($ratings) {
                                $subQuery->select('score_value')
                                    ->from('user_sport')
                                    ->whereIn('score_value', $ratings);
                            });
                    });
                }
            )
            ->when(
                !empty($filters['online_recently']) && $filters['online_recently'] == true,
                fn($query) => $query->where(
                    'last_login',
                    '>=',
                    Carbon::now()->subMinutes($filters['online_before_minutes'] ?? 30)
                )
            )
            ->when(
                !empty($filters['same_club_id']) && is_array($filters['same_club_id']),
                fn($query) => $query->whereHas('clubs', function ($q) use ($filters) {
                    $q->whereIn('clubs.id', $filters['same_club_id']);
                })
            )
            ->when(
                isset($filters['verify_profile']),
                fn($query) => $query->where('is_profile_completed', $filters['verify_profile'])
            )
            ->when(
                !empty($filters['achievement']) && $filters['achievement'] == true,
                fn($query) => $query->where(function ($q) {
                    $q->whereHas('badges')
                        ->orWhereHas('sport', fn($q2) => $q2->whereNotNull('user_sport.tier')); // hoặc có tier
                })
            );
        if (!empty($filters['recent_matches']) && is_array($filters['recent_matches'])) {
            $query->withCount([
                'matches' => fn($q) => $q->where('status', 'completed')
                    ->whereMonth('matches.created_at', now()->month)
                    ->whereYear('matches.created_at', now()->year),
                'miniMatches' => fn($q) => $q->where('status', 'completed')
                    ->whereMonth('mini_matches.created_at', now()->month)
                    ->whereYear('mini_matches.created_at', now()->year),
            ]);

            $query->where(function ($q) use ($filters) {
                foreach ($filters['recent_matches'] as $opt) {
                    if ($opt === 'high') {
                        $q->orWhereRaw('(COALESCE(matches_count, 0) + COALESCE(mini_matches_count, 0)) > 12');
                    } elseif ($opt === 'medium') {
                        $q->orWhereRaw('(COALESCE(matches_count, 0) + COALESCE(mini_matches_count, 0)) BETWEEN 5 AND 12');
                    } elseif ($opt === 'low') {
                        $q->orWhereRaw('(COALESCE(matches_count, 0) + COALESCE(mini_matches_count, 0)) <= 4');
                    }
                }
            });
        }
    }

    public function scopeNearBy($query, float $lat, float $lng, float $radiusKm)
    {
        $haversine = "(6371 * acos(cos(radians($lat))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians($lng))
                + sin(radians($lat))
                * sin(radians(latitude))))";

        return $query->select('*')
            ->selectRaw("$haversine AS distance")
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');
    }

    public function scopeOrderByDistance($query, $lat, $lng)
    {
        return $query
            ->select('*')
            ->selectRaw("
                (
                    6371 * acos(
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )
                ) AS distance
            ", [$lat, $lng, $lat])
            ->orderByRaw('latitude IS NULL OR longitude IS NULL')
            ->orderBy('distance', 'asc');
    }
    // User.php
    public function isFriendWith(User $otherUser): bool
    {
        return $this->followings()
            ->where('followable_id', $otherUser->id)
            ->where('followable_type', User::class)
            ->exists()
            && $otherUser->followings()
                ->where('followable_id', $this->id)
                ->where('followable_type', User::class)
                ->exists();
    }

    public function isFollowing(?User $otherUser): bool
    {
        if (!$otherUser) {
            return false;
        }
        return $this->followings()
            ->where('followable_id', $otherUser->id)
            ->where('followable_type', User::class)
            ->exists();
    }

    public function friends()
    {
        $userClass = config('auth.providers.users.model', User::class);
        $userId = $this->id;

        return User::query()
            ->whereExists(function ($q) use ($userId, $userClass) {
                $q->select(DB::raw(1))
                    ->from('follows as f1')
                    ->whereColumn('f1.followable_id', 'users.id')
                    ->where('f1.user_id', $userId)
                    ->where('followable_type', $userClass); // không alias ở đây
            })
            ->whereExists(function ($q) use ($userId, $userClass) {
                $q->select(DB::raw(1))
                    ->from('follows as f2')
                    ->whereColumn('f2.user_id', 'users.id')
                    ->where('f2.followable_id', $userId)
                    ->where('followable_type', $userClass); // không alias ở đây
            });
    }

    public function vnduprScoresBySport($sportId = null)
    {
        $query = $this->hasManyThrough(
            UserSportScore::class,
            UserSport::class,
            'user_id',
            'user_sport_id',
            'id',
            'id'
        )->where('score_type', 'vndupr_score');

        if ($sportId) {
            $query->where('user_sport.sport_id', $sportId);
        }

        return $query;
    }

    // User.php
    public function scores()
    {
        return $this->hasManyThrough(
            UserSportScore::class,
            UserSport::class,
            'user_id',       // khóa ngoại ở bảng trung gian (user_sport)
            'user_sport_id', // khóa ngoại ở bảng cuối cùng (user_sport_score)
            'id',            // local key của User
            'id'             // local key của UserSport
        );
    }

    public function getVNRank($sportId)
    {
        if (!$sportId) {
            return null;
        }

        $userScore = $this->vnduprScoresBySport($sportId)->max('score_value') ?? 0;

        return self::query()
            ->where('total_matches', '>', 5)
            ->select(DB::raw('COUNT(DISTINCT users.id) + 1 as `rank`'))
            ->join('user_sport', 'users.id', '=', 'user_sport.user_id')
            ->join('user_sport_scores', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->where('user_sport_scores.score_value', '>', $userScore)
            ->value('rank');
    }

    public static function isAdmin($userId)
    {
        return User::where('id', $userId)->where('role', self::ADMIN)->exists();
    }

    public static function isSuperAdmin($userId)
    {
        return User::where('id', $userId)->where('is_super_admin', true)->exists();
    }

    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    public function scopeNotBanned($query)
    {
        return $query->where('is_banned', false);
    }

    public function scopeKeyword($query, ?string $keyword)
    {
        if (empty($keyword)) {
            return $query;
        }
        return $query->where(function ($q) use ($keyword) {
            $q->where('full_name', 'like', "%{$keyword}%")
              ->orWhere('phone', 'like', "%{$keyword}%")
              ->orWhere('email', 'like', "%{$keyword}%");
        });
    }

    public function scopeApplyTimeline($query, ?string $timeFilter, ?int $userId = null)
    {
        if (!$timeFilter || $timeFilter === 'all') {
            return $query;
        }

        $userId = $userId ?? auth()->id();

        return match ($timeFilter) {
            'mine' => $query->whereHas('clubs', function ($q) use ($userId) {
                $q->whereIn('clubs.id', DB::table('club_members')
                    ->where('user_id', $userId)
                    ->where('membership_status', ClubMembershipStatus::Joined->value)
                    ->pluck('club_id'));
            }),
            'friends' => $query->whereIn('id', (new User)->find($userId)?->friends()?->pluck('id') ?? []),
            default => $query,
        };
    }

    public function getTotalTournamentsAttribute(): int
    {
        $participantIds = DB::table('participants as p')
            ->where('p.user_id', $this->id)
            ->whereRaw('EXISTS (SELECT 1 FROM tournaments t WHERE t.id = p.tournament_id AND t.status != 1 AND t.start_date <= NOW())')
            ->pluck('p.tournament_id');

        $staffTournamentIds = DB::table('tournament_staff as ts')
            ->where('ts.user_id', $this->id)
            ->whereIn('ts.role', [1, 2])
            ->whereRaw('EXISTS (SELECT 1 FROM tournaments t WHERE t.id = ts.tournament_id AND t.status != 1 AND t.start_date <= NOW())')
            ->pluck('ts.tournament_id');

        $allIds = $participantIds->merge($staffTournamentIds)->unique();

        return $allIds->count();
    }

    public function getTotalMiniTournamentsAttribute(): int
    {
        $participantIds = DB::table('mini_participants as mp')
            ->where('mp.user_id', $this->id)
            ->whereRaw('EXISTS (SELECT 1 FROM mini_tournaments mt WHERE mt.id = mp.mini_tournament_id AND mt.status != 1 AND mt.start_time <= NOW())')
            ->pluck('mp.mini_tournament_id');

        $staffMiniTournamentIds = DB::table('mini_tournament_staff as mts')
            ->where('mts.user_id', $this->id)
            ->whereIn('mts.role', [1])
            ->whereRaw('EXISTS (SELECT 1 FROM mini_tournaments mt WHERE mt.id = mts.mini_tournament_id AND mt.status != 1 AND mt.start_time <= NOW())')
            ->pluck('mts.mini_tournament_id');

        $allIds = $participantIds->merge($staffMiniTournamentIds)->unique();

        return $allIds->count();
    }

    /**
     * Tính thống kê sport (total_matches, total_tournaments, win_rate, performance)
     * cho một user + sport_id cụ thể.
     *
     * @param bool $isOwnProfile Nếu true: tính cả tournament private của chính mình và không loại ongoing.
     *                           Nếu false: loại bỏ tournament private (khi xem profile người khác),
     *                           đồng thời loại bỏ ongoing tournament để align với list API.
     */
    public static function getSportStats(int $userId, int $sportId, bool $isOwnProfile = true): array
    {
        // Tournament matches - tách home và away
        $homeMatches = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.home_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.home_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id', 'm.winner_id', 'm.home_team_id', 'm.away_team_id')
            ->get()
            ->keyBy('id');

        $awayMatches = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.away_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.away_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id', 'm.winner_id', 'm.home_team_id', 'm.away_team_id')
            ->get()
            ->keyBy('id');

        $matches = $homeMatches->merge($awayMatches)->unique('id');

        // Mini tournament matches - tách team1 và team2
        $minisTeam1 = DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team1_id')
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->where('mtm.user_id', $userId)
            ->select('mm.id', 'mm.team_win_id', 'mm.participant_win_id', 'mm.team1_id', 'mm.team2_id', 'mm.participant1_id', 'mm.participant2_id')
            ->get()
            ->keyBy('id');

        $minisTeam2 = DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team2_id')
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->where('mtm.user_id', $userId)
            ->select('mm.id', 'mm.team_win_id', 'mm.participant_win_id', 'mm.team1_id', 'mm.team2_id', 'mm.participant1_id', 'mm.participant2_id')
            ->get()
            ->keyBy('id');

        $minis = $minisTeam1->merge($minisTeam2)->unique('id');

        // Xác định user thuộc mini match nào (team-based hoặc solo)
        $miniTeamMemberRows = DB::table('mini_team_members')
            ->whereIn('mini_team_id', $minis->pluck('team1_id')->merge($minis->pluck('team2_id'))->filter()->unique())
            ->get()
            ->groupBy('mini_team_id');

        $miniParticipantRows = DB::table('mini_participants')
            ->whereIn('id', $minis->pluck('participant1_id')->merge($minis->pluck('participant2_id'))->filter()->unique())
            ->get()
            ->keyBy('id');

        // Quick matches
        $qmHistories = DB::table('match_histories')
            ->where('user_id', $userId)
            ->whereNotNull('quick_match_id')
            ->get();

        $qmIds = $qmHistories->pluck('quick_match_id')->unique();
        $quickMatches = DB::table('quick_matches')
            ->whereIn('id', $qmIds)
            ->where('status', 'completed')
            ->get()
            ->filter(function ($qm) use ($sportId) {
                if ($qm->competition_location_id) {
                    $hasSport = DB::table('competition_location_sport')
                        ->where('competition_location_id', $qm->competition_location_id)
                        ->where('sport_id', $sportId)
                        ->exists();
                    return $hasSport;
                }
                return DB::table('user_sport')
                    ->where('user_id', $qm->created_by)
                    ->where('sport_id', $sportId)
                    ->exists();
            })
            ->keyBy('id');

        // === TÍNH STATS ===
        $tournamentMatches = 0;
        $tournamentWins = 0;
        $miniMatches = 0;
        $miniWins = 0;

        // Tournament matches
        foreach ($matches as $m) {
            $isInHome = false;
            $isInAway = false;
            if ($m->home_team_id && $m->away_team_id) {
                $homeUserIds = self::getTeamMemberIds($m->home_team_id);
                $awayUserIds = self::getTeamMemberIds($m->away_team_id);
                $isInHome = in_array($userId, $homeUserIds);
                $isInAway = in_array($userId, $awayUserIds);
                if (!$isInHome && !$isInAway) continue;
            } elseif (!$m->home_team_id && !$m->away_team_id) {
                continue;
            } elseif ($m->home_team_id) {
                $homeUserIds = self::getTeamMemberIds($m->home_team_id);
                $isInHome = in_array($userId, $homeUserIds);
                if (!$isInHome) continue;
            } else {
                $awayUserIds = self::getTeamMemberIds($m->away_team_id);
                $isInAway = in_array($userId, $awayUserIds);
                if (!$isInAway) continue;
            }
            $tournamentMatches++;
            if ($m->winner_id) {
                if ($isInHome && $m->winner_id == $m->home_team_id) $tournamentWins++;
                if ($isInAway && $m->winner_id == $m->away_team_id) $tournamentWins++;
            }
        }

        // Mini matches
        foreach ($minis as $mm) {
            $isTeam1 = isset($miniTeamMemberRows[$mm->team1_id])
                && in_array($userId, $miniTeamMemberRows[$mm->team1_id]->pluck('user_id')->all());
            $isTeam2 = isset($miniTeamMemberRows[$mm->team2_id])
                && in_array($userId, $miniTeamMemberRows[$mm->team2_id]->pluck('user_id')->all());
            $isParticipant1 = $mm->participant1_id
                && isset($miniParticipantRows[$mm->participant1_id])
                && $miniParticipantRows[$mm->participant1_id]->user_id == $userId;
            $isParticipant2 = $mm->participant2_id
                && isset($miniParticipantRows[$mm->participant2_id])
                && $miniParticipantRows[$mm->participant2_id]->user_id == $userId;

            if (!$isTeam1 && !$isTeam2 && !$isParticipant1 && !$isParticipant2) continue;

            $miniMatches++;

            if ($mm->team_win_id) {
                if ($isTeam1 && $mm->team_win_id == $mm->team1_id) $miniWins++;
                if ($isTeam2 && $mm->team_win_id == $mm->team2_id) $miniWins++;
            }
            if ($mm->participant_win_id) {
                if ($isParticipant1 && $mm->participant_win_id == $mm->participant1_id) $miniWins++;
                if ($isParticipant2 && $mm->participant_win_id == $mm->participant2_id) $miniWins++;
            }
        }

        // Quick matches
        $qmMatches = 0;
        $qmWins = 0;
        foreach ($quickMatches as $qm) {
            $teamA = is_array($qm->team_a) ? $qm->team_a : json_decode($qm->team_a ?? '[]', true);
            $teamB = is_array($qm->team_b) ? $qm->team_b : json_decode($qm->team_b ?? '[]', true);
            $isInTeamA = in_array($userId, $teamA ?: []);
            $isInTeamB = in_array($userId, $teamB ?: []);
            if (!$isInTeamA && !$isInTeamB) continue;
            $qmMatches++;
            if ($qm->winner === 'team_a' && $isInTeamA) $qmWins++;
            if ($qm->winner === 'team_b' && $isInTeamB) $qmWins++;
        }

        $totalMatches = $tournamentMatches + $miniMatches + $qmMatches;
        $totalWins = $tournamentWins + $miniWins + $qmWins;
        $winRate = $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100, 2) : 0;

        // Performance: 10 trận gần nhất, đếm số wins
        $recentMatches = [];

        // Tournament - tách home và away
        $tRecentHome = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.home_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.home_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id', 'm.winner_id', 'm.home_team_id', 'm.away_team_id', 'm.scheduled_at as dt')
            ->get();

        $tRecentAway = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.away_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.away_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id', 'm.winner_id', 'm.home_team_id', 'm.away_team_id', 'm.scheduled_at as dt')
            ->get();

        $tRecent = $tRecentHome->merge($tRecentAway)->unique('id');
        foreach ($tRecent as $m) {
            $recentMatches[] = ['type' => 't', 'dt' => $m->dt, 'is_win' => self::isUserWinTournamentMatch($m, $userId)];
        }

        // Mini - tách team1 và team2
        $mRecentTeam1 = DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team1_id')
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->where('mtm.user_id', $userId)
            ->select('mm.id', 'mm.team_win_id', 'mm.participant_win_id', 'mm.team1_id', 'mm.team2_id', 'mm.participant1_id', 'mm.participant2_id', 'mm.created_at as dt')
            ->get();

        $mRecentTeam2 = DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team2_id')
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->where('mtm.user_id', $userId)
            ->select('mm.id', 'mm.team_win_id', 'mm.participant_win_id', 'mm.team1_id', 'mm.team2_id', 'mm.participant1_id', 'mm.participant2_id', 'mm.created_at as dt')
            ->get();

        $mRecent = $mRecentTeam1->merge($mRecentTeam2)->unique('id');
        foreach ($mRecent as $mm) {
            $recentMatches[] = ['type' => 'mnt', 'dt' => $mm->dt, 'is_win' => self::isUserWinMiniMatch($mm, $userId, $miniTeamMemberRows, $miniParticipantRows)];
        }

        // Quick
        foreach ($quickMatches as $qm) {
            $teamA = is_array($qm->team_a) ? $qm->team_a : json_decode($qm->team_a ?? '[]', true);
            $teamB = is_array($qm->team_b) ? $qm->team_b : json_decode($qm->team_b ?? '[]', true);
            $isInTeamA = in_array($userId, $teamA ?: []);
            $isInTeamB = in_array($userId, $teamB ?: []);
            if (!$isInTeamA && !$isInTeamB) continue;
            $isWin = ($qm->winner === 'team_a' && $isInTeamA) || ($qm->winner === 'team_b' && $isInTeamB);
            $recentMatches[] = ['type' => 'qm', 'dt' => $qm->confirmed_at, 'is_win' => $isWin];
        }

        usort($recentMatches, fn($a, $b) => (strtotime($b['dt'] ?? 0) - strtotime($a['dt'] ?? 0)));
        $performance = 0;
        foreach (array_slice($recentMatches, 0, 10) as $m) {
            if ($m['is_win']) $performance++;
        }

        // total_tournaments & total_mini_tournaments (giữ nguyên logic cũ)
        $filterPrivate = $isOwnProfile ? '' : ' AND t.is_private = 0';
        $filterMntPrivate = $isOwnProfile ? '' : ' AND mnt.is_private = 0';
        $filterOngoingT = $isOwnProfile ? '' : ' AND NOT (t.status = 2 AND t.start_date <= NOW() AND t.end_date IS NOT NULL AND t.end_date >= NOW())';
        $filterOngoingMnt = $isOwnProfile ? '' : ' AND NOT (mnt.status = 2 AND mnt.start_time <= NOW() AND mnt.end_time IS NOT NULL AND mnt.end_time >= NOW())';

        $tRows = DB::select("SELECT DISTINCT id FROM (
            SELECT p.tournament_id AS id FROM participants p JOIN tournaments t ON t.id = p.tournament_id
            WHERE p.user_id = ? AND p.is_confirmed = 1 AND t.sport_id = ? AND t.status != 1 AND t.start_date <= NOW()
            {$filterOngoingT} {$filterPrivate}
            UNION
            SELECT ts.tournament_id AS id FROM tournament_staff ts JOIN tournaments t ON t.id = ts.tournament_id
            WHERE ts.user_id = ? AND ts.role IN (1,2) AND t.sport_id = ? AND t.status != 1 AND t.start_date <= NOW()
            {$filterOngoingT} {$filterPrivate}
        ) AS combined", [$userId, $sportId, $userId, $sportId]);
        $totalTournaments = count($tRows);

        $mntRows = DB::select("SELECT DISTINCT id FROM (
            SELECT mp.mini_tournament_id AS id FROM mini_participants mp JOIN mini_tournaments mnt ON mnt.id = mp.mini_tournament_id
            WHERE mp.user_id = ? AND mp.is_confirmed = 1 AND mnt.sport_id = ? AND mnt.status != 1 AND mnt.start_time <= NOW()
            {$filterOngoingMnt} {$filterMntPrivate}
            UNION
            SELECT mts.mini_tournament_id AS id FROM mini_tournament_staff mts JOIN mini_tournaments mnt ON mnt.id = mts.mini_tournament_id
            WHERE mts.user_id = ? AND mts.role = 1 AND mnt.sport_id = ? AND mnt.status != 1 AND mnt.start_time <= NOW()
            {$filterOngoingMnt} {$filterMntPrivate}
        ) AS combined", [$userId, $sportId, $userId, $sportId]);
        $totalMiniTournaments = count($mntRows);

        return [
            'total_matches' => $totalMatches,
            'total_tournaments' => $totalTournaments,
            'total_mini_tournaments' => $totalMiniTournaments,
            'total_prizes' => 0,
            'win_rate' => $winRate,
            'performance' => $performance,
        ];
    }

    private static function getTeamMemberIds(int $teamId): array
    {
        return DB::table('team_members')->where('team_id', $teamId)->pluck('user_id')->all();
    }

    private static function isUserWinTournamentMatch($match, int $userId): bool
    {
        if (!$match->winner_id) return false;
        $homeIds = self::getTeamMemberIds($match->home_team_id);
        $awayIds = self::getTeamMemberIds($match->away_team_id);
        $isInHome = in_array($userId, $homeIds);
        $isInAway = in_array($userId, $awayIds);
        if (!$isInHome && !$isInAway) return false;
        if ($isInHome && $match->winner_id == $match->home_team_id) return true;
        if ($isInAway && $match->winner_id == $match->away_team_id) return true;
        return false;
    }

    private static function isUserWinMiniMatch($mm, int $userId, $miniTeamMembers, $miniParticipants): bool
    {
        $isTeam1 = isset($miniTeamMembers[$mm->team1_id]) && in_array($userId, $miniTeamMembers[$mm->team1_id]->pluck('user_id')->all());
        $isTeam2 = isset($miniTeamMembers[$mm->team2_id]) && in_array($userId, $miniTeamMembers[$mm->team2_id]->pluck('user_id')->all());
        $isParticipant1 = $mm->participant1_id && isset($miniParticipants[$mm->participant1_id]) && $miniParticipants[$mm->participant1_id]->user_id == $userId;
        $isParticipant2 = $mm->participant2_id && isset($miniParticipants[$mm->participant2_id]) && $miniParticipants[$mm->participant2_id]->user_id == $userId;
        if (!$isTeam1 && !$isTeam2 && !$isParticipant1 && !$isParticipant2) return false;
        if ($mm->team_win_id) {
            if ($isTeam1 && $mm->team_win_id == $mm->team1_id) return true;
            if ($isTeam2 && $mm->team_win_id == $mm->team2_id) return true;
        }
        if ($mm->participant_win_id) {
            if ($isParticipant1 && $mm->participant_win_id == $mm->participant1_id) return true;
            if ($isParticipant2 && $mm->participant_win_id == $mm->participant2_id) return true;
        }
        return false;
    }

    /**
     * Fetch sport stats for multiple users in a single query (avoids N+1).
     * Returns an array keyed by user_id.
     */
    public static function getBatchSportStats(array $userIds, int $sportId, bool $isOwnProfile = true): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userIds = array_values(array_unique($userIds));
        // Used in raw SQL: tm.user_id IN ({$userIdsCsv}) — safe, IDs are validated ints
        $userIdsCsv = implode(',', $userIds);

        // Filter strings
        $tPrivateFilter = $isOwnProfile ? '' : ' AND t.is_private = 0';
        $mntPrivateFilter = $isOwnProfile ? '' : ' AND mnt.is_private = 0';
        $ongoingT = $isOwnProfile ? '' : ' AND NOT (t.status = 2 AND t.start_date <= NOW() AND t.end_date IS NOT NULL AND t.end_date >= NOW())';
        $ongoingMnt = $isOwnProfile ? '' : ' AND NOT (mnt.status = 2 AND mnt.start_time <= NOW() AND mnt.end_time IS NOT NULL AND mnt.end_time >= NOW())';
        $tPrivateAndOngoing = $tPrivateFilter . $ongoingT;
        $mntPrivateAndOngoing = $mntPrivateFilter . $ongoingMnt;

        // Main stats query — SUM aggregates grouped by user
        $statsRowsSql = "
            SELECT
                user_id,
                SUM(t_matches) AS total_tournament_matches,
                SUM(w_t_matches) AS tournament_wins,
                SUM(mini_matches) AS total_mini_matches,
                SUM(w_mini_matches) AS mini_wins,
                SUM(qm_matches) AS total_qm_matches,
                SUM(w_qm_matches) AS qm_wins
            FROM (
                SELECT tm.user_id AS user_id,
                    1 AS t_matches, 0 AS mini_matches, 0 AS qm_matches,
                    CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END AS w_t_matches,
                    0 AS w_mini_matches, 0 AS w_qm_matches
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.home_team_id
                WHERE tm.user_id IN ({$userIdsCsv}) AND t.sport_id = ? AND m.status = 'completed'
                " . $tPrivateAndOngoing . "

                UNION ALL

                SELECT tm.user_id,
                    1, 0, 0,
                    CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END, 0, 0
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.away_team_id
                WHERE tm.user_id IN ({$userIdsCsv}) AND t.sport_id = ? AND m.status = 'completed'
                " . $tPrivateAndOngoing . "

                UNION ALL

                SELECT mtm.user_id,
                    0, 1, 0, 0,
                    CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                WHERE mtm.user_id IN ({$userIdsCsv}) AND mnt.sport_id = ? AND mm.status = 'completed'
                " . $mntPrivateAndOngoing . "

                UNION ALL

                SELECT mtm.user_id,
                    0, 1, 0, 0,
                    CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
                WHERE mtm.user_id IN ({$userIdsCsv}) AND mnt.sport_id = ? AND mm.status = 'completed'
                " . $mntPrivateAndOngoing . "

                UNION ALL

                SELECT mh.user_id,
                    0, 0, 1, 0, 0,
                    CASE WHEN qm.winner = 'team_a' THEN 1 ELSE 0 END
                FROM match_histories mh
                JOIN quick_matches qm ON mh.quick_match_id = qm.id
                LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
                LEFT JOIN users u ON qm.created_by = u.id
                LEFT JOIN user_sport usc ON u.id = usc.user_id
                WHERE mh.user_id IN ({$userIdsCsv})
                  AND qm.status = 'completed'
                  AND JSON_CONTAINS(qm.team_a, CAST(mh.user_id AS CHAR))
                  AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))

                UNION ALL

                SELECT mh.user_id,
                    0, 0, 1, 0, 0,
                    CASE WHEN qm.winner = 'team_b' THEN 1 ELSE 0 END
                FROM match_histories mh
                JOIN quick_matches qm ON mh.quick_match_id = qm.id
                LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
                LEFT JOIN users u ON qm.created_by = u.id
                LEFT JOIN user_sport usc ON u.id = usc.user_id
                WHERE mh.user_id IN ({$userIdsCsv})
                  AND qm.status = 'completed'
                  AND JSON_CONTAINS(qm.team_b, CAST(mh.user_id AS CHAR))
                  AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))
            ) AS all_matches
            GROUP BY user_id
        ";
        $statsRows = DB::select($statsRowsSql, [
            $sportId, $sportId, $sportId, $sportId,
            $sportId, $sportId, $sportId, $sportId,
        ]);

        // Performance: wins in last 10 matches per user
        $perfRowsSql = "
            SELECT user_id, SUM(wins) AS wins
            FROM (
                SELECT tm.user_id,
                    CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END AS wins,
                    m.scheduled_at AS dt
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.winner_id
                WHERE tm.user_id IN ({$userIdsCsv}) AND t.sport_id = ? AND m.status = 'completed'
                  AND m.winner_id IS NOT NULL " . $tPrivateAndOngoing . "

                UNION ALL

                SELECT mtm.user_id,
                    CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END AS wins,
                    mm.created_at AS dt
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team_win_id
                WHERE mtm.user_id IN ({$userIdsCsv}) AND mnt.sport_id = ? AND mm.status = 'completed'
                  AND mm.team_win_id IS NOT NULL " . $mntPrivateAndOngoing . "

                UNION ALL

                SELECT mh.user_id,
                    CASE WHEN qm.winner = 'team_a' THEN 1 ELSE 0 END AS wins,
                    qm.confirmed_at AS dt
                FROM match_histories mh
                JOIN quick_matches qm ON mh.quick_match_id = qm.id
                LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
                LEFT JOIN users u ON qm.created_by = u.id
                LEFT JOIN user_sport usc ON u.id = usc.user_id
                WHERE mh.user_id IN ({$userIdsCsv}) AND qm.status = 'completed'
                  AND qm.winner IS NOT NULL
                  AND JSON_CONTAINS(qm.team_a, CAST(mh.user_id AS CHAR))
                  AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))

                UNION ALL

                SELECT mh.user_id,
                    CASE WHEN qm.winner = 'team_b' THEN 1 ELSE 0 END AS wins,
                    qm.confirmed_at AS dt
                FROM match_histories mh
                JOIN quick_matches qm ON mh.quick_match_id = qm.id
                LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
                LEFT JOIN users u ON qm.created_by = u.id
                LEFT JOIN user_sport usc ON u.id = usc.user_id
                WHERE mh.user_id IN ({$userIdsCsv}) AND qm.status = 'completed'
                  AND qm.winner IS NOT NULL
                  AND JSON_CONTAINS(qm.team_b, CAST(mh.user_id AS CHAR))
                  AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))
            ) AS recent
            GROUP BY user_id
        ";
        $perfRows = DB::select($perfRowsSql, [$sportId, $sportId, $sportId, $sportId, $sportId, $sportId]);

        // Tournament counts
        $tournamentSql = "
            SELECT user_id, COUNT(*) AS cnt FROM (
                SELECT p.user_id, p.tournament_id AS id FROM participants p
                JOIN tournaments t ON t.id = p.tournament_id
                WHERE p.user_id IN ({$userIdsCsv}) AND p.is_confirmed = 1
                  AND t.sport_id = ? AND t.status != 1 AND t.start_date <= NOW()
                  " . $tPrivateAndOngoing . "
                  AND (
                    EXISTS (SELECT 1 FROM tournament_types tt
                      JOIN `groups` g ON g.tournament_type_id = tt.id
                      JOIN matches m ON m.group_id = g.id
                      WHERE tt.tournament_id = t.id AND tt.format = 2 AND m.round = 4
                        AND EXISTS (SELECT 1 FROM match_results mr WHERE mr.match_id = m.id))
                    OR NOT EXISTS (SELECT 1 FROM tournament_types tt WHERE tt.tournament_id = t.id AND tt.format = 2)
                    OR NOT EXISTS (SELECT 1 FROM tournament_types tt JOIN `groups` g ON g.tournament_type_id = tt.id JOIN matches m ON m.group_id = g.id WHERE tt.tournament_id = t.id)
                  )
                UNION
                SELECT ts.user_id, ts.tournament_id AS id FROM tournament_staff ts
                JOIN tournaments t ON t.id = ts.tournament_id
                WHERE ts.user_id IN ({$userIdsCsv}) AND ts.role IN (1,2)
                  AND t.sport_id = ? AND t.status != 1 AND t.start_date <= NOW()
                  " . $tPrivateAndOngoing . "
                  AND (
                    EXISTS (SELECT 1 FROM tournament_types tt
                      JOIN `groups` g ON g.tournament_type_id = tt.id
                      JOIN matches m ON m.group_id = g.id
                      WHERE tt.tournament_id = t.id AND tt.format = 2 AND m.round = 4
                        AND EXISTS (SELECT 1 FROM match_results mr WHERE mr.match_id = m.id))
                    OR NOT EXISTS (SELECT 1 FROM tournament_types tt WHERE tt.tournament_id = t.id AND tt.format = 2)
                    OR NOT EXISTS (SELECT 1 FROM tournament_types tt JOIN `groups` g ON g.tournament_type_id = tt.id JOIN matches m ON m.group_id = g.id WHERE tt.tournament_id = t.id)
                  )
            ) AS combined GROUP BY user_id
        ";
        $tournamentRows = DB::select($tournamentSql, [$sportId, $sportId]);

        // Mini-tournament counts
        $miniTournamentSql = "
            SELECT user_id, COUNT(*) AS cnt FROM (
                SELECT mp.user_id, mp.mini_tournament_id AS id FROM mini_participants mp
                JOIN mini_tournaments mnt ON mnt.id = mp.mini_tournament_id
                WHERE mp.user_id IN ({$userIdsCsv}) AND mp.is_confirmed = 1
                  AND mnt.sport_id = ? AND mnt.status != 1 AND mnt.start_time <= NOW()
                  " . $mntPrivateAndOngoing . "
                UNION
                SELECT mts.user_id, mts.mini_tournament_id AS id FROM mini_tournament_staff mts
                JOIN mini_tournaments mnt ON mnt.id = mts.mini_tournament_id
                WHERE mts.user_id IN ({$userIdsCsv}) AND mts.role = 1
                  AND mnt.sport_id = ? AND mnt.status != 1 AND mnt.start_time <= NOW()
                  " . $mntPrivateAndOngoing . "
            ) AS combined GROUP BY user_id
        ";
        $miniTournamentRows = DB::select($miniTournamentSql, [$sportId, $sportId]);

        // Build lookup maps
        $perfMap = [];
        foreach ($perfRows as $r) {
            $perfMap[$r->user_id] = (int) $r->wins;
        }

        $tournamentMap = [];
        foreach ($tournamentRows as $r) {
            $tournamentMap[$r->user_id] = (int) $r->cnt;
        }

        $miniTournamentMap = [];
        foreach ($miniTournamentRows as $r) {
            $miniTournamentMap[$r->user_id] = (int) $r->cnt;
        }

        // Assemble results
        $result = [];
        foreach ($userIds as $uid) {
            $result[$uid] = [
                'total_matches' => 0,
                'total_tournaments' => $tournamentMap[$uid] ?? 0,
                'total_mini_tournaments' => $miniTournamentMap[$uid] ?? 0,
                'total_prizes' => 0,
                'win_rate' => 0.0,
                'performance' => $perfMap[$uid] ?? 0,
            ];
        }

        foreach ($statsRows as $row) {
            $uid = $row->user_id;
            if (!isset($result[$uid])) {
                continue;
            }
            $tournamentMatches = (int) ($row->total_tournament_matches ?? 0);
            $tournamentWins = (int) ($row->tournament_wins ?? 0);
            $miniMatches = (int) ($row->total_mini_matches ?? 0);
            $miniWins = (int) ($row->mini_wins ?? 0);
            $qmMatches = (int) ($row->total_qm_matches ?? 0);
            $qmWins = (int) ($row->qm_wins ?? 0);

            $totalMatches = $tournamentMatches + $miniMatches + $qmMatches;
            $totalWins = $tournamentWins + $miniWins + $qmWins;
            $winRate = $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100, 2) : 0;

            $result[$uid]['total_matches'] = $totalMatches;
            $result[$uid]['win_rate'] = $winRate;
        }

        return $result;
    }
}
