<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MiniMatch extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'mini_tournament_id',
        'team1_id',
        'team2_id',
        'participant1_id',
        'participant2_id',
        'status',
        'participant_win_id',
        'team_win_id',
        'team1_confirm',
        'team2_confirm',
        'name',
    ];

    const PER_PAGE = 10;

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DISPUTED = 'disputed';

    public function participant1()
    {
        return $this->belongsTo(MiniParticipant::class, 'participant1_id');
    }
    public function participant2()
    {
        return $this->belongsTo(MiniParticipant::class, 'participant2_id');
    }

    public function team1()
    {
        return $this->belongsTo(MiniTeam::class,'team1_id');
    }
    public function team2()
    {
        return $this->belongsTo(MiniTeam::class,'team2_id');
    }

    public function teamWin() 
    {
        return $this->belongsTo(MiniTeam::class,'team_win_id');
    }

    public function participantWin()
    {
        return $this->belongsTo(MiniParticipant::class, 'participant_win_id');
    }
    public function results()
    {
        return $this->hasMany(MiniMatchResult::class, 'mini_match_id');
    }

    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class, 'mini_tournament_id');
    }

    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_COMPLETED;
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'team1.members.user.sports.scores',
            'team2.members.user.sports.scores',
            'team1.members.user.sports.sport',
            'team2.members.user.sports.sport',
            'results.team.members.user',
            'miniTournament.competitionLocation',
            'miniTournament.sport',
        ]);
    }
    public function scopeLoadFullRelations()
    {
        return $this->load([
            'team1.members.user.sports.scores',
            'team2.members.user.sports.scores',
            'team1.members.user.sports.sport',
            'team2.members.user.sports.sport',
            'results.team.members.user',
            'miniTournament.competitionLocation',
            'miniTournament.sport',
        ]);
    }

    public function scopeInBounds($query, $minLat, $maxLat, $minLng, $maxLng)
    {
        return $query->whereHas('miniTournament.competitionLocation', function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
            $q->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng]);
        });
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when(
                !empty($filters['sport_id']),
                fn($q) => $q->whereHas(
                    'miniTournament.sport',
                    fn($sq) => $sq->where('sports.id', $filters['sport_id'])
                )
            )
            ->when(
                !empty($filters['location_id']),
                fn($q) => $q->whereHas(
                    'miniTournament.competitionLocation',
                    fn($lq) => $lq->where('location_id', $filters['location_id'])
                )
            )
            ->when(
                !empty($filters['keyword']),
                fn($q) => $q->where('name', 'like', '%' . $filters['keyword'] . '%')
                    ->orWhereHas('miniTournament', function ($sub) use ($filters) {
                        $sub->where('name', 'like', '%' . $filters['keyword'] . '%')
                            ->orWhereHas('competitionLocation', function ($locSub) use ($filters) {
                                $locSub->where('name', 'like', '%' . $filters['keyword'] . '%')
                                    ->orWhere('address', 'like', '%' . $filters['keyword'] . '%')
                                    ->orWhereHas(
                                        'location',
                                        fn($lq) => $lq->where('name', 'like', '%' . $filters['keyword'] . '%')
                                    );
                            });
                    })
            )
            ->when(
                !empty($filters['type']) && is_array($filters['type']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters) {
                        foreach ($filters['type'] as $type) {
                            if ($type === 'single') {
                                $subQuery->orWhere(function ($sub) {
                                    $sub->whereHas('participant1', fn($p1) => $p1->whereNotNull('user_id')->whereNull('team_id'))
                                        ->orWhereHas('participant2', fn($p2) => $p2->whereNotNull('user_id')->whereNull('team_id'));
                                });
                            } elseif ($type === 'double') {
                                $subQuery->orWhere(function ($sub) {
                                    $sub->whereHas('participant1', fn($p1) => $p1->whereNotNull('team_id')->whereNull('user_id'))
                                        ->orWhereHas('participant2', fn($p2) => $p2->whereNotNull('team_id')->whereNull('user_id'));
                                });
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filters['rating']),
                function ($q) use ($filters) {
                    $ratings = is_array($filters['rating']) ? $filters['rating'] : [$filters['rating']];
                    $q->whereHas('miniTournament', function ($tq) use ($ratings) {
                        foreach ($ratings as $rating) {
                            $rating = (float) $rating;
                            $tq->orWhere(function ($subQuery) use ($rating) {
                                $subQuery->where('min_rating', '<=', $rating)
                                    ->where('max_rating', '>=', $rating);
                            });
                        }
                    });
                }
            )
            ->when(
                !empty($filters['fee']) && is_array($filters['fee']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters) {
                        foreach ($filters['fee'] as $feeType) {
                            if ($feeType === 'free') {
                                $subQuery->orWhereHas('miniTournament', fn($tq) => $tq->where('fee_amount', 0));
                            } elseif ($feeType === 'paid') {
                                $subQuery->orWhereHas('miniTournament', function ($tq) use ($filters) {
                                    $tq->whereBetween('fee_amount', [$filters['min_price'], $filters['max_price']]);
                                });
                            }
                        }
                    });
                }
            )
            ->when(
                !empty($filters['slot_status']) && is_array($filters['slot_status']),
                function ($q) use ($filters) {
                    $q->where(function ($subQuery) use ($filters) {
                        foreach ($filters['slot_status'] as $slotStatus) {
                            if ($slotStatus === 'one_slot') {
                                $subQuery->orWhereRaw('(CASE WHEN participant1_id IS NULL THEN 1 ELSE 0 END + CASE WHEN participant2_id IS NULL THEN 1 ELSE 0 END) = 1');
                            } elseif ($slotStatus === 'two_slot') {
                                $subQuery->orWhere(function ($sub) {
                                    $sub->whereHas('participant1', fn($p) => $p->whereNotNull('team_id'))
                                        ->orWhereHas('participant2', fn($p) => $p->whereNotNull('team_id'));
                                });
                            } elseif ($slotStatus === 'full_slot') {
                                $subQuery->orWhere(function ($sub) {
                                    $sub->whereNull('participant1_id')
                                        ->whereNull('participant2_id');
                                });
                            }
                        }
                    });
                }
            );
    }

    public function scopeNearBy($query, float $lat, float $lng, float $radiusKm = 5)
    {
        $haversine = "(6371 * acos(cos(radians($lat)) 
                * cos(radians(latitude)) 
                * cos(radians(longitude) - radians($lng)) 
                + sin(radians($lat)) 
                * sin(radians(latitude))))";

        return $query->whereHas('miniTournament.competitionLocation', function ($q) use ($haversine, $radiusKm) {
            $q->select('*')
                ->whereRaw("$haversine < ?", [$radiusKm]);
        });
    }
}
