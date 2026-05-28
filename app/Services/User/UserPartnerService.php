<?php

namespace App\Services\User;

use App\Models\MatchHistory;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\MiniMatch;
use App\Models\MiniMatchResult;
use App\Models\MiniTeam;
use App\Models\QuickMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserPartnerService
{
    public function getTopPartners(int $userId, int $limit = 3): array
    {
        $partnerStats = $this->buildPartnerStats($userId);

        return $partnerStats
            ->sortByDesc(fn($p) => [$p['win_rate'], $p['total_matches']])
            ->take($limit)
            ->values()
            ->all();
    }

    public function getTopOpponents(int $userId, int $limit = 3): array
    {
        $opponentStats = $this->buildOpponentStats($userId);

        return $opponentStats
            ->sortBy(fn($o) => [$o['win_rate'], -$o['total_matches']])
            ->take($limit)
            ->values()
            ->all();
    }

    private function buildPartnerStats(int $userId): Collection
    {
        $stats = [];

        // Quick matches
        $this->accumulateQuickMatchPartners($userId, $stats);

        // Tournament matches
        $this->accumulateTournamentPartners($userId, $stats);

        // Mini-tournament matches
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

        // Quick matches
        $this->accumulateQuickMatchOpponents($userId, $stats);

        // Tournament matches
        $this->accumulateTournamentOpponents($userId, $stats);

        // Mini-tournament matches
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
        $teamIds = DB::table('team_members')->where('user_id', $userId)->pluck('team_id');
        if ($teamIds->isEmpty()) {
            return;
        }

        $matches = Matches::with([
                'homeTeam.members',
                'awayTeam.members',
                'results',
            ])
            ->whereIn('home_team_id', $teamIds)
            ->orWhereIn('away_team_id', $teamIds)
            ->whereNotNull('winner_id')
            ->get();

        foreach ($matches as $match) {
            $homeUserIds = $match->homeTeam->members->pluck('id')->all();
            $awayUserIds = $match->awayTeam->members->pluck('id')->all();
            $isHome = in_array($userId, $homeUserIds);
            $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
            $partnerIds = $isHome
                ? collect($homeUserIds)->filter(fn($id) => $id != $userId)->all()
                : collect($awayUserIds)->filter(fn($id) => $id != $userId)->all();

            $won = $match->winner_id === $myTeamId;

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
        $teamIds = DB::table('team_members')->where('user_id', $userId)->pluck('team_id');
        if ($teamIds->isEmpty()) {
            return;
        }

        $matches = Matches::with([
                'homeTeam.members',
                'awayTeam.members',
            ])
            ->whereIn('home_team_id', $teamIds)
            ->orWhereIn('away_team_id', $teamIds)
            ->whereNotNull('winner_id')
            ->get();

        foreach ($matches as $match) {
            $homeUserIds = $match->homeTeam->members->pluck('id')->all();
            $awayUserIds = $match->awayTeam->members->pluck('id')->all();
            $isHome = in_array($userId, $homeUserIds);
            $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
            $opponentUserIds = $isHome ? $awayUserIds : $homeUserIds;

            $won = $match->winner_id === $myTeamId;

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
        $miniTeamIds = DB::table('mini_team_members')->where('user_id', $userId)->pluck('mini_team_id');
        if ($miniTeamIds->isEmpty()) {
            return;
        }

        $minis = MiniMatch::with([
                'team1.members',
                'team2.members',
            ])
            ->whereIn('team1_id', $miniTeamIds)
            ->orWhereIn('team2_id', $miniTeamIds)
            ->whereNotNull('team_win_id')
            ->get();

        foreach ($minis as $mini) {
            $t1MemberIds = $mini->team1->members->pluck('id')->all();
            $t2MemberIds = $mini->team2->members->pluck('id')->all();
            $isTeam1 = in_array($userId, $t1MemberIds);
            $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
            $partnerIds = $isTeam1
                ? collect($t1MemberIds)->filter(fn($id) => $id != $userId)->all()
                : collect($t2MemberIds)->filter(fn($id) => $id != $userId)->all();

            $won = $mini->team_win_id === $myTeamId;

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
        $miniTeamIds = DB::table('mini_team_members')->where('user_id', $userId)->pluck('mini_team_id');
        if ($miniTeamIds->isEmpty()) {
            return;
        }

        $minis = MiniMatch::with([
                'team1.members',
                'team2.members',
            ])
            ->whereIn('team1_id', $miniTeamIds)
            ->orWhereIn('team2_id', $miniTeamIds)
            ->whereNotNull('team_win_id')
            ->get();

        foreach ($minis as $mini) {
            $t1MemberIds = $mini->team1->members->pluck('id')->all();
            $t2MemberIds = $mini->team2->members->pluck('id')->all();
            $isTeam1 = in_array($userId, $t1MemberIds);
            $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
            $opponentMemberIds = $isTeam1 ? $t2MemberIds : $t1MemberIds;

            $won = $mini->team_win_id === $myTeamId;

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
