<?php

namespace App\Services\User;

use App\Models\MatchHistory;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTeamMember;
use App\Models\QuickMatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserPartnerService
{
    private const SPORT_ID = 1;

    public function getTopPartners(int $userId, int $page = 1, int $perPage = 10): array
    {
        $stats = $this->buildPartnerStats($userId);

        $sorted = $stats
            ->sortByDesc(fn($p) => [$p['win_rate'], $p['total_matches']])
            ->values();

        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $sorted->slice($offset, $perPage)->values()->all(),
            'total' => $total,
        ];
    }

    public function getTopOpponents(int $userId, int $page = 1, int $perPage = 10): array
    {
        $stats = $this->buildOpponentStats($userId);

        $sorted = $stats
            ->sortBy(fn($o) => [$o['win_rate'], -$o['total_matches']])
            ->values();

        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $sorted->slice($offset, $perPage)->values()->all(),
            'total' => $total,
        ];
    }

    private function buildPartnerStats(int $userId): Collection
    {
        $stats = [];

        $this->accumulateQuickMatchPartners($userId, $stats);
        $this->accumulateTournamentPartners($userId, $stats);
        $this->accumulateMiniTournamentPartners($userId, $stats);

        return collect($stats)->map(function ($data) {
            $data['losses'] = $data['total_matches'] - $data['wins'];
            $data['win_rate'] = $data['total_matches'] > 0
                ? round(($data['wins'] / $data['total_matches']) * 100, 2)
                : 0.0;
            return $data;
        });
    }

    private function buildOpponentStats(int $userId): Collection
    {
        $stats = [];

        $this->accumulateQuickMatchOpponents($userId, $stats);
        $this->accumulateTournamentOpponents($userId, $stats);
        $this->accumulateMiniTournamentOpponents($userId, $stats);

        return collect($stats)->map(function ($data) {
            $data['losses'] = $data['total_matches'] - $data['wins'];
            $data['win_rate'] = $data['total_matches'] > 0
                ? round(($data['wins'] / $data['total_matches']) * 100, 2)
                : 0.0;
            return $data;
        });
    }

    // -------------------------------------------------------------------------
    // Quick Matches
    // -------------------------------------------------------------------------

    private function accumulateQuickMatchPartners(int $userId, array &$stats): void
    {
        $histories = MatchHistory::where('user_id', $userId)
            ->whereNotNull('quick_match_id')
            ->get()
            ->keyBy('quick_match_id');

        if ($histories->isEmpty()) {
            return;
        }

        $quickMatches = QuickMatch::whereIn('id', $histories->keys())
            ->where('status', QuickMatch::STATUS_COMPLETED)
            ->get()
            ->keyBy('id');

        foreach ($histories as $qmId => $history) {
            $qm = $quickMatches->get($qmId);
            if (!$qm) {
                continue;
            }

            $userSide = $history->team_side;
            $partnerIds = $userSide === 'team_a'
                ? collect($qm->team_a ?? [])->filter(fn($id) => $id != $userId)->all()
                : collect($qm->team_b ?? [])->filter(fn($id) => $id != $userId)->all();

            $won = $qm->winner === $userSide;

            foreach ($partnerIds as $partnerId) {
                if (empty($partnerId)) {
                    continue;
                }
                if (!isset($stats[$partnerId])) {
                    $stats[$partnerId] = ['user_id' => $partnerId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$partnerId]['total_matches']++;
                if ($won) {
                    $stats[$partnerId]['wins']++;
                }
            }
        }
    }

    private function accumulateQuickMatchOpponents(int $userId, array &$stats): void
    {
        $histories = MatchHistory::where('user_id', $userId)
            ->whereNotNull('quick_match_id')
            ->get()
            ->keyBy('quick_match_id');

        if ($histories->isEmpty()) {
            return;
        }

        $quickMatches = QuickMatch::whereIn('id', $histories->keys())
            ->where('status', QuickMatch::STATUS_COMPLETED)
            ->get()
            ->keyBy('id');

        foreach ($histories as $qmId => $history) {
            $qm = $quickMatches->get($qmId);
            if (!$qm) {
                continue;
            }

            $userSide = $history->team_side;
            $opponentIds = $userSide === 'team_a'
                ? collect($qm->team_b ?? [])->all()
                : collect($qm->team_a ?? [])->all();

            $won = $qm->winner === $userSide;

            foreach ($opponentIds as $oppId) {
                if (empty($oppId)) {
                    continue;
                }
                if (!isset($stats[$oppId])) {
                    $stats[$oppId] = ['user_id' => $oppId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$oppId]['total_matches']++;
                if ($won) {
                    $stats[$oppId]['wins']++;
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Tournament Matches
    // -------------------------------------------------------------------------

    private function accumulateTournamentPartners(int $userId, array &$stats): void
    {
        $sportId = self::SPORT_ID;

        $homeMatchIds = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.home_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.home_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id')
            ->pluck('id');

        $awayMatchIds = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.away_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.away_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id')
            ->pluck('id');

        $matchIds = $homeMatchIds->merge($awayMatchIds)->unique();
        if ($matchIds->isEmpty()) {
            return;
        }

        $allTeamIds = Matches::whereIn('id', $matchIds)
            ->get()
            ->flatMap(fn($m) => [$m->home_team_id, $m->away_team_id])
            ->filter()
            ->unique();

        $teamMembersByTeam = DB::table('team_members')
            ->join('users', 'team_members.user_id', '=', 'users.id')
            ->whereIn('team_id', $allTeamIds)
            ->where('users.is_guest', 0)
            ->select('team_members.team_id', 'team_members.user_id')
            ->get()
            ->groupBy('team_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all());

        $matches = Matches::whereIn('id', $matchIds)->get()->keyBy('id');

        foreach ($matches as $match) {
            $homeUserIds = $teamMembersByTeam[$match->home_team_id] ?? [];
            $awayUserIds = $teamMembersByTeam[$match->away_team_id] ?? [];

            $isHome = in_array($userId, $homeUserIds);
            $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
            $partnerIds = $isHome
                ? collect($homeUserIds)->filter(fn($id) => $id != $userId)->all()
                : collect($awayUserIds)->filter(fn($id) => $id != $userId)->all();

            $won = $match->winner_id == $myTeamId;

            foreach ($partnerIds as $partnerId) {
                if (!isset($stats[$partnerId])) {
                    $stats[$partnerId] = ['user_id' => $partnerId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$partnerId]['total_matches']++;
                if ($won) {
                    $stats[$partnerId]['wins']++;
                }
            }
        }
    }

    private function accumulateTournamentOpponents(int $userId, array &$stats): void
    {
        $sportId = self::SPORT_ID;

        $homeMatchIds = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.home_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.home_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id')
            ->pluck('id');

        $awayMatchIds = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 'm.away_team_id')
            ->where('tm.user_id', $userId)
            ->whereColumn('tm.team_id', 'm.away_team_id')
            ->where('t.sport_id', $sportId)
            ->where('m.status', 'completed')
            ->select('m.id')
            ->pluck('id');

        $matchIds = $homeMatchIds->merge($awayMatchIds)->unique();
        if ($matchIds->isEmpty()) {
            return;
        }

        $allTeamIds = Matches::whereIn('id', $matchIds)
            ->get()
            ->flatMap(fn($m) => [$m->home_team_id, $m->away_team_id])
            ->filter()
            ->unique();

        $teamMembersByTeam = DB::table('team_members')
            ->join('users', 'team_members.user_id', '=', 'users.id')
            ->whereIn('team_id', $allTeamIds)
            ->where('users.is_guest', 0)
            ->select('team_members.team_id', 'team_members.user_id')
            ->get()
            ->groupBy('team_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all());

        $matches = Matches::whereIn('id', $matchIds)->get()->keyBy('id');

        foreach ($matches as $match) {
            $homeUserIds = $teamMembersByTeam[$match->home_team_id] ?? [];
            $awayUserIds = $teamMembersByTeam[$match->away_team_id] ?? [];

            $isHome = in_array($userId, $homeUserIds);
            $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
            $opponentUserIds = $isHome ? $awayUserIds : $homeUserIds;

            $won = $match->winner_id == $myTeamId;

            foreach ($opponentUserIds as $oppId) {
                if (!isset($stats[$oppId])) {
                    $stats[$oppId] = ['user_id' => $oppId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$oppId]['total_matches']++;
                if ($won) {
                    $stats[$oppId]['wins']++;
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Mini-Tournament Matches
    // -------------------------------------------------------------------------

    private function accumulateMiniTournamentPartners(int $userId, array &$stats): void
    {
        $sportId = self::SPORT_ID;

        $miniIds = DB::table('mini_team_members')
            ->join('mini_teams', 'mini_team_members.mini_team_id', '=', 'mini_teams.id')
            ->join('mini_matches', function ($join) {
                $join->on('mini_matches.team1_id', '=', 'mini_teams.id')
                    ->orOn('mini_matches.team2_id', '=', 'mini_teams.id');
            })
            ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
            ->where('mini_team_members.user_id', $userId)
            ->where('mini_tournaments.sport_id', $sportId)
            ->where('mini_matches.status', 'completed')
            ->select('mini_matches.id')
            ->distinct()
            ->pluck('id');

        if ($miniIds->isEmpty()) {
            return;
        }

        $minis = MiniMatch::whereIn('id', $miniIds)->get()->keyBy('id');

        $miniTeamMemberIds = $minis->pluck('team1_id')
            ->merge($minis->pluck('team2_id'))
            ->filter()
            ->unique();

        $miniTeamMembersByTeam = DB::table('mini_team_members')
            ->whereIn('mini_team_id', $miniTeamMemberIds)
            ->where('is_guest', 0)
            ->get()
            ->groupBy('mini_team_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all());

        foreach ($minis as $mini) {
            $t1MemberIds = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
            $t2MemberIds = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

            $isTeam1 = in_array($userId, $t1MemberIds);
            $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
            $partnerIds = $isTeam1
                ? collect($t1MemberIds)->filter(fn($id) => $id != $userId)->all()
                : collect($t2MemberIds)->filter(fn($id) => $id != $userId)->all();

            $won = $mini->team_win_id == $myTeamId;

            foreach ($partnerIds as $partnerId) {
                if (!isset($stats[$partnerId])) {
                    $stats[$partnerId] = ['user_id' => $partnerId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$partnerId]['total_matches']++;
                if ($won) {
                    $stats[$partnerId]['wins']++;
                }
            }
        }
    }

    private function accumulateMiniTournamentOpponents(int $userId, array &$stats): void
    {
        $sportId = self::SPORT_ID;

        $miniIds = DB::table('mini_team_members')
            ->join('mini_teams', 'mini_team_members.mini_team_id', '=', 'mini_teams.id')
            ->join('mini_matches', function ($join) {
                $join->on('mini_matches.team1_id', '=', 'mini_teams.id')
                    ->orOn('mini_matches.team2_id', '=', 'mini_teams.id');
            })
            ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
            ->where('mini_team_members.user_id', $userId)
            ->where('mini_tournaments.sport_id', $sportId)
            ->where('mini_matches.status', 'completed')
            ->select('mini_matches.id')
            ->distinct()
            ->pluck('id');

        if ($miniIds->isEmpty()) {
            return;
        }

        $minis = MiniMatch::whereIn('id', $miniIds)->get()->keyBy('id');

        $miniTeamMemberIds = $minis->pluck('team1_id')
            ->merge($minis->pluck('team2_id'))
            ->filter()
            ->unique();

        $miniTeamMembersByTeam = DB::table('mini_team_members')
            ->whereIn('mini_team_id', $miniTeamMemberIds)
            ->where('is_guest', 0)
            ->get()
            ->groupBy('mini_team_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all());

        foreach ($minis as $mini) {
            $t1MemberIds = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
            $t2MemberIds = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

            $isTeam1 = in_array($userId, $t1MemberIds);
            $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
            $opponentMemberIds = $isTeam1 ? $t2MemberIds : $t1MemberIds;

            $won = $mini->team_win_id == $myTeamId;

            foreach ($opponentMemberIds as $oppId) {
                if (!isset($stats[$oppId])) {
                    $stats[$oppId] = ['user_id' => $oppId, 'total_matches' => 0, 'wins' => 0];
                }
                $stats[$oppId]['total_matches']++;
                if ($won) {
                    $stats[$oppId]['wins']++;
                }
            }
        }
    }
}
