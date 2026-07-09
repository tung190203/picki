<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UserSport extends Model
{
    use HasFactory;

    protected $table = 'user_sport';

    protected $fillable = [
        'user_id',
        'sport_id',
        'tier',
        'total_matches',
    ];

    protected $appends = ['win_rate'];

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
        return self::calculateWinRate($this->user_id, $this->sport_id);
    }

    public static function calculateWinRate(int $userId, int $sportId): float
    {
        $cacheKey = "user_sport_win_rate:{$userId}:{$sportId}";

        return Cache::remember($cacheKey, 3600, function () use ($userId, $sportId) {
            $result = DB::select("
                SELECT 
                    SUM(total_matches) as total_matches,
                    SUM(wins) as wins
                FROM (
                    -- Tournament home matches
                    SELECT COUNT(DISTINCT m.id) as total_matches, 0 as wins
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.home_team_id
                    WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed'

                    UNION ALL

                    -- Tournament away matches
                    SELECT COUNT(DISTINCT m.id) as total_matches, 0 as wins
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.away_team_id
                    WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed'

                    UNION ALL

                    -- Mini tournament team1 matches
                    SELECT COUNT(DISTINCT mm.id) as total_matches, 0 as wins
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                    WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed'

                    UNION ALL

                    -- Mini tournament team2 matches
                    SELECT COUNT(DISTINCT mm.id) as total_matches, 0 as wins
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
                    WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed'

                    UNION ALL

                    -- Tournament home wins
                    SELECT 0 as total_matches, COUNT(DISTINCT m.id) as wins
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.winner_id AND tm.user_id = ?
                    WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed' AND m.winner_id IS NOT NULL

                    UNION ALL

                    -- Tournament away wins
                    SELECT 0 as total_matches, COUNT(DISTINCT m.id) as wins
                    FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.winner_id AND tm.user_id = ?
                    WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed' AND m.winner_id IS NOT NULL

                    UNION ALL

                    -- Mini tournament team1 wins
                    SELECT 0 as total_matches, COUNT(DISTINCT mm.id) as wins
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team_win_id
                    WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed' AND mm.team_win_id IS NOT NULL

                    UNION ALL

                    -- Mini tournament team2 wins
                    SELECT 0 as total_matches, COUNT(DISTINCT mm.id) as wins
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team_win_id
                    WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed' AND mm.team_win_id IS NOT NULL
                ) as combined
            ", [
                $userId, $sportId,
                $userId, $sportId,
                $userId, $sportId,
                $userId, $sportId,
                $userId, $userId, $sportId,
                $userId, $userId, $sportId,
                $userId, $sportId,
                $userId, $sportId,
            ]);

            if (empty($result) || $result[0]->total_matches == 0) {
                return 0.0;
            }

            return round(($result[0]->wins / $result[0]->total_matches) * 100, 2);
        });
    }

    public static function invalidateWinRateCache(int $userId, ?int $sportId = null): void
    {
        if ($sportId) {
            Cache::forget("user_sport_win_rate:{$userId}:{$sportId}");
        } else {
            Cache::flush();
        }
    }
}
