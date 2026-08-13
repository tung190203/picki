<?php

namespace App\Services\Admin\UserMerge;

use App\Models\Matches;
use App\Models\MatchHistory;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use Illuminate\Support\Facades\DB;

class DuplicateMatchDetector
{
    public function detect(int $userAId, int $userBId): array
    {
        $duplicates = [];

        $tournamentDuplicates = $this->detectTournamentDuplicates($userAId, $userBId);
        $quickMatchDuplicates = $this->detectQuickMatchDuplicates($userAId, $userBId);
        $miniTournamentDuplicates = $this->detectMiniTournamentDuplicates($userAId, $userBId);

        return array_merge($tournamentDuplicates, $quickMatchDuplicates, $miniTournamentDuplicates);
    }

    protected function detectTournamentDuplicates(int $userAId, int $userBId): array
    {
        $duplicates = [];

        $tournamentMatches = DB::table('matches as m')
            ->join('team_members as tm_a', function ($join) use ($userAId) {
                $join->on('tm_a.team_id', '=', 'm.home_team_id')
                    ->where('tm_a.user_id', '=', $userAId);
            })
            ->join('team_members as tm_b', function ($join) use ($userBId) {
                $join->on('tm_b.team_id', '=', 'm.away_team_id')
                    ->where('tm_b.user_id', '=', $userBId);
            })
            ->join('tournament_types as tt', 'tt.id', '=', 'm.tournament_type_id')
            ->join('tournaments as t', 't.id', '=', 'tt.tournament_id')
            ->select([
                'm.id as match_id',
                'm.name_of_match',
                'm.scheduled_at',
                'm.status',
                't.id as tournament_id',
                't.name as tournament_name',
            ])
            ->get();

        foreach ($tournamentMatches as $match) {
            $duplicates[] = [
                'match_id' => $match->match_id,
                'match_type' => 'tournament',
                'match_name' => $match->name_of_match ?? $match->tournament_name,
                'tournament_id' => $match->tournament_id,
                'tournament_name' => $match->tournament_name,
                'played_at' => $match->scheduled_at,
                'reason' => "Cùng trận đấu giải đấu: {$match->tournament_name}",
            ];
        }

        $reverseMatches = DB::table('matches as m')
            ->join('team_members as tm_a', function ($join) use ($userAId) {
                $join->on('tm_a.team_id', '=', 'm.away_team_id')
                    ->where('tm_a.user_id', '=', $userAId);
            })
            ->join('team_members as tm_b', function ($join) use ($userBId) {
                $join->on('tm_b.team_id', '=', 'm.home_team_id')
                    ->where('tm_b.user_id', '=', $userBId);
            })
            ->join('tournament_types as tt', 'tt.id', '=', 'm.tournament_type_id')
            ->join('tournaments as t', 't.id', '=', 'tt.tournament_id')
            ->select([
                'm.id as match_id',
                'm.name_of_match',
                'm.scheduled_at',
                'm.status',
                't.id as tournament_id',
                't.name as tournament_name',
            ])
            ->get();

        foreach ($reverseMatches as $match) {
            $duplicates[] = [
                'match_id' => $match->match_id,
                'match_type' => 'tournament',
                'match_name' => $match->name_of_match ?? $match->tournament_name,
                'tournament_id' => $match->tournament_id,
                'tournament_name' => $match->tournament_name,
                'played_at' => $match->scheduled_at,
                'reason' => "Cùng trận đấu giải đấu: {$match->tournament_name}",
            ];
        }

        $sameTeamMatches = DB::table('matches as m')
            ->join('team_members as tm_a', function ($join) use ($userAId) {
                $join->on('tm_a.team_id', '=', 'm.home_team_id')
                    ->where('tm_a.user_id', '=', $userAId);
            })
            ->join('team_members as tm_b', function ($join) use ($userBId) {
                $join->on('tm_b.team_id', '=', 'm.home_team_id')
                    ->where('tm_b.user_id', '=', $userBId);
            })
            ->join('tournament_types as tt', 'tt.id', '=', 'm.tournament_type_id')
            ->join('tournaments as t', 't.id', '=', 'tt.tournament_id')
            ->select([
                'm.id as match_id',
                'm.name_of_match',
                'm.scheduled_at',
                'm.status',
                't.id as tournament_id',
                't.name as tournament_name',
            ])
            ->get();

        foreach ($sameTeamMatches as $match) {
            $existing = collect($duplicates)->firstWhere('match_id', $match->match_id);
            if (!$existing) {
                $duplicates[] = [
                    'match_id' => $match->match_id,
                    'match_type' => 'tournament',
                    'match_name' => $match->name_of_match ?? $match->tournament_name,
                    'tournament_id' => $match->tournament_id,
                    'tournament_name' => $match->tournament_name,
                    'played_at' => $match->scheduled_at,
                    'reason' => "Cùng đội trong giải đấu: {$match->tournament_name}",
                ];
            }
        }

        $sameTeamAway = DB::table('matches as m')
            ->join('team_members as tm_a', function ($join) use ($userAId) {
                $join->on('tm_a.team_id', '=', 'm.away_team_id')
                    ->where('tm_a.user_id', '=', $userAId);
            })
            ->join('team_members as tm_b', function ($join) use ($userBId) {
                $join->on('tm_b.team_id', '=', 'm.away_team_id')
                    ->where('tm_b.user_id', '=', $userBId);
            })
            ->join('tournament_types as tt', 'tt.id', '=', 'm.tournament_type_id')
            ->join('tournaments as t', 't.id', '=', 'tt.tournament_id')
            ->select([
                'm.id as match_id',
                'm.name_of_match',
                'm.scheduled_at',
                'm.status',
                't.id as tournament_id',
                't.name as tournament_name',
            ])
            ->get();

        foreach ($sameTeamAway as $match) {
            $existing = collect($duplicates)->firstWhere('match_id', $match->match_id);
            if (!$existing) {
                $duplicates[] = [
                    'match_id' => $match->match_id,
                    'match_type' => 'tournament',
                    'match_name' => $match->name_of_match ?? $match->tournament_name,
                    'tournament_id' => $match->tournament_id,
                    'tournament_name' => $match->tournament_name,
                    'played_at' => $match->scheduled_at,
                    'reason' => "Cùng đội trong giải đấu: {$match->tournament_name}",
                ];
            }
        }

        return $duplicates;
    }

    protected function detectQuickMatchDuplicates(int $userAId, int $userBId): array
    {
        $duplicates = [];

        $quickMatches = DB::table('match_histories as mh_a')
            ->join('match_histories as mh_b', 'mh_a.quick_match_id', '=', 'mh_b.quick_match_id')
            ->join('quick_matches as qm', 'qm.id', '=', 'mh_a.quick_match_id')
            ->where('mh_a.user_id', $userAId)
            ->where('mh_b.user_id', $userBId)
            ->where('mh_a.id', '!=', 'mh_b.id')
            ->select([
                'qm.id as match_id',
                'qm.name as match_name',
                'qm.scheduled_at',
                'qm.status',
            ])
            ->distinct()
            ->get();

        foreach ($quickMatches as $match) {
            $duplicates[] = [
                'match_id' => $match->match_id,
                'match_type' => 'quick_match',
                'match_name' => $match->match_name ?? 'Trận nhanh',
                'tournament_id' => null,
                'tournament_name' => null,
                'played_at' => $match->scheduled_at,
                'reason' => 'Cùng tham gia trận quick match',
            ];
        }

        return $duplicates;
    }

    protected function detectMiniTournamentDuplicates(int $userAId, int $userBId): array
    {
        $duplicates = [];

        $miniMatches = DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm_a', function ($join) use ($userAId) {
                $join->on('mtm_a.mini_team_id', '=', 'mm.team1_id')
                    ->where('mtm_a.user_id', '=', $userAId);
            })
            ->join('mini_team_members as mtm_b', function ($join) use ($userBId) {
                $join->on('mtm_b.mini_team_id', '=', 'mm.team2_id')
                    ->where('mtm_b.user_id', '=', $userBId);
            })
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mm.mini_tournament_id')
            ->select([
                'mm.id as match_id',
                'mnt.id as tournament_id',
                'mnt.name as tournament_name',
                'mm.round_number',
                'mm.status',
            ])
            ->get();

        foreach ($miniMatches as $match) {
            $duplicates[] = [
                'match_id' => $match->match_id,
                'match_type' => 'mini_tournament',
                'match_name' => "Vòng {$match->round_number}: {$match->tournament_name}",
                'tournament_id' => $match->tournament_id,
                'tournament_name' => $match->tournament_name,
                'played_at' => null,
                'reason' => "Cùng trận mini tournament: {$match->tournament_name}",
            ];
        }

        $reverseMiniMatches = DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm_a', function ($join) use ($userAId) {
                $join->on('mtm_a.mini_team_id', '=', 'mm.team2_id')
                    ->where('mtm_a.user_id', '=', $userAId);
            })
            ->join('mini_team_members as mtm_b', function ($join) use ($userBId) {
                $join->on('mtm_b.mini_team_id', '=', 'mm.team1_id')
                    ->where('mtm_b.user_id', '=', $userBId);
            })
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mm.mini_tournament_id')
            ->select([
                'mm.id as match_id',
                'mnt.id as tournament_id',
                'mnt.name as tournament_name',
                'mm.round_number',
                'mm.status',
            ])
            ->get();

        foreach ($reverseMiniMatches as $match) {
            $duplicates[] = [
                'match_id' => $match->match_id,
                'match_type' => 'mini_tournament',
                'match_name' => "Vòng {$match->round_number}: {$match->tournament_name}",
                'tournament_id' => $match->tournament_id,
                'tournament_name' => $match->tournament_name,
                'played_at' => null,
                'reason' => "Cùng trận mini tournament: {$match->tournament_name}",
            ];
        }

        $sameMiniTeamMatches = DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm_a', function ($join) use ($userAId) {
                $join->on('mtm_a.mini_team_id', '=', 'mm.team1_id')
                    ->where('mtm_a.user_id', '=', $userAId);
            })
            ->join('mini_team_members as mtm_b', function ($join) use ($userBId) {
                $join->on('mtm_b.mini_team_id', '=', 'mm.team1_id')
                    ->where('mtm_b.user_id', '=', $userBId);
            })
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mm.mini_tournament_id')
            ->select([
                'mm.id as match_id',
                'mnt.id as tournament_id',
                'mnt.name as tournament_name',
                'mm.round_number',
                'mm.status',
            ])
            ->get();

        foreach ($sameMiniTeamMatches as $match) {
            $existing = collect($duplicates)->firstWhere('match_id', $match->match_id);
            if (!$existing) {
                $duplicates[] = [
                    'match_id' => $match->match_id,
                    'match_type' => 'mini_tournament',
                    'match_name' => "Vòng {$match->round_number}: {$match->tournament_name}",
                    'tournament_id' => $match->tournament_id,
                    'tournament_name' => $match->tournament_name,
                    'played_at' => null,
                    'reason' => "Cùng đội trong mini tournament: {$match->tournament_name}",
                ];
            }
        }

        $sameMiniTeam2Matches = DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm_a', function ($join) use ($userAId) {
                $join->on('mtm_a.mini_team_id', '=', 'mm.team2_id')
                    ->where('mtm_a.user_id', '=', $userAId);
            })
            ->join('mini_team_members as mtm_b', function ($join) use ($userBId) {
                $join->on('mtm_b.mini_team_id', '=', 'mm.team2_id')
                    ->where('mtm_b.user_id', '=', $userBId);
            })
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mm.mini_tournament_id')
            ->select([
                'mm.id as match_id',
                'mnt.id as tournament_id',
                'mnt.name as tournament_name',
                'mm.round_number',
                'mm.status',
            ])
            ->get();

        foreach ($sameMiniTeam2Matches as $match) {
            $existing = collect($duplicates)->firstWhere('match_id', $match->match_id);
            if (!$existing) {
                $duplicates[] = [
                    'match_id' => $match->match_id,
                    'match_type' => 'mini_tournament',
                    'match_name' => "Vòng {$match->round_number}: {$match->tournament_name}",
                    'tournament_id' => $match->tournament_id,
                    'tournament_name' => $match->tournament_name,
                    'played_at' => null,
                    'reason' => "Cùng đội trong mini tournament: {$match->tournament_name}",
                ];
            }
        }

        return $duplicates;
    }

    public function getMatchCounts(int $userId): array
    {
        $tournamentMatches = $this->countTournamentMatches($userId);
        $quickMatches = $this->countQuickMatches($userId);
        $miniMatches = $this->countMiniTournamentMatches($userId);

        return [
            'tournament' => $tournamentMatches,
            'quick_match' => $quickMatches,
            'mini_tournament' => $miniMatches,
            'total' => $tournamentMatches + $quickMatches + $miniMatches,
        ];
    }

    protected function countTournamentMatches(int $userId): int
    {
        return DB::table('matches as m')
            ->join('team_members as tm', function ($join) use ($userId) {
                $join->on(function ($q) use ($userId) {
                    $q->where('tm.team_id', '=', 'm.home_team_id')
                        ->orWhere('tm.team_id', '=', 'm.away_team_id');
                })
                    ->where('tm.user_id', '=', $userId);
            })
            ->distinct('m.id')
            ->count('m.id');
    }

    protected function countQuickMatches(int $userId): int
    {
        return DB::table('match_histories')
            ->where('user_id', $userId)
            ->distinct('quick_match_id')
            ->count('quick_match_id');
    }

    protected function countMiniTournamentMatches(int $userId): int
    {
        return DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm', function ($join) use ($userId) {
                $join->on(function ($q) use ($userId) {
                    $q->where('mtm.mini_team_id', '=', 'mm.team1_id')
                        ->orWhere('mtm.mini_team_id', '=', 'mm.team2_id');
                })
                    ->where('mtm.user_id', '=', $userId);
            })
            ->distinct('mm.id')
            ->count('mm.id');
    }
}
