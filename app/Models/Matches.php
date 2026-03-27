<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;
    protected $table = 'matches';

    protected $fillable = [
        'group_id',
        'tournament_type_id',
        'round',
        'next_match_id',
        'next_position',
        'loser_next_match_id',
        'loser_next_position',
        'home_team_id',
        'away_team_id',
        'leg',
        'referee_id',
        'status',
        'is_bye',
        'is_loser_bracket',
        'is_third_place',
        'scheduled_at',
        'court',
        'winner_id',
        'name_of_match',
        'best_loser_source_round',
        'best_loser_rank',
    ];

    const PER_PAGE = 15;

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DISPUTED = 'disputed';
    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function results()
    {
        return $this->hasMany(MatchResult::class, 'match_id');
    }

    public function poolAdvancementRules()
    {
        return $this->hasMany(PoolAdvancementRule::class, 'next_match_id');
    }

    public function vnduprHistory()
    {
        return $this->hasMany(VnduprHistory::class, 'match_id');
    }

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
    public function referee()
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($match) {
            $match->results()->delete();
            $match->poolAdvancementRules()->delete();
            $match->vnduprHistory()->delete();
        });
    }

    public function scopeWithFullRelations($query)
    {
        return $query->with([
            'group',
            'referee',
            'homeTeam.members.sports.scores',
            'homeTeam.members.sports.sport',
            'awayTeam.members.sports.scores',
            'awayTeam.members.sports.sport',
            'results',
            'tournamentType.tournament'
        ]);
    }
}
