<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserSport extends Model
{
    use HasFactory;

    protected $table = 'user_sport';

    protected $fillable = [
        'user_id',
        'sport_id',
        'tier'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function sport()
    {
        return $this->belongsTo(Sport::class);
    }
    
    public function scores()
    {
        return $this->hasMany(UserSportScore::class);
    }

    public function getWinRateAttribute(): float
    {
        $userId = $this->user_id;
        $sportId = $this->sport_id;

        $matchCount = \DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.home_team_id')
            ->where('tm.user_id', $userId)
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->count(DB::raw('DISTINCT m.id'));

        $awayMatchCount = \DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.away_team_id')
            ->where('tm.user_id', $userId)
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->count(DB::raw('DISTINCT m.id'));

        $miniMatchCount = \DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team1_id')
            ->where('mtm.user_id', $userId)
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->count(DB::raw('DISTINCT mm.id'));

        $awayMiniMatchCount = \DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team2_id')
            ->where('mtm.user_id', $userId)
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->count(DB::raw('DISTINCT mm.id'));

        $totalMatches = ($matchCount + $awayMatchCount) + ($miniMatchCount + $awayMiniMatchCount);

        if ($totalMatches === 0) {
            return 0.0;
        }

        $wins = \DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', function ($join) use ($userId) {
                $join->on('tm.team_id', '=', 'm.winner_id')
                    ->where('tm.user_id', $userId);
            })
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->whereNotNull('m.winner_id')
            ->count(DB::raw('DISTINCT m.id'));

        $miniWins = \DB::table('mini_matches as mm')
            ->join('mini_tournaments as mnt', 'mm.mini_tournament_id', '=', 'mnt.id')
            ->join('mini_team_members as mtm', function ($join) use ($userId) {
                $join->on('mtm.mini_team_id', '=', 'mm.team_win_id')
                    ->where('mtm.user_id', $userId);
            })
            ->where('mnt.sport_id', $sportId)
            ->where('mm.status', 'completed')
            ->whereNotNull('mm.team_win_id')
            ->count(DB::raw('DISTINCT mm.id'));

        return round((($wins + $miniWins) / $totalMatches) * 100, 2);
    }
}
