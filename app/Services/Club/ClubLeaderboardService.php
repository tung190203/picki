<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\MatchHistory;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\Sport;
use App\Models\VnduprHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClubLeaderboardService
{
    /**
     * Tính rank của club dựa trên tổng điểm members trong tháng
     * Cache 5 phút để tránh load toàn bộ clubs mỗi request
     */
    public function calculateClubRank(Club $club, ?int $month = null, ?int $year = null): ?int
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $cacheKey = "club_rank:{$club->id}:{$year}:{$month}";
        return Cache::remember($cacheKey, 300, fn () => $this->computeClubRank($club, $month, $year));
    }

    private function computeClubRank(Club $club, int $month, int $year): ?int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $allClubs = Club::where('status', \App\Enums\ClubStatus::Active)
            ->with(['joinedMembers.user.sports.scores'])
            ->get();

        $clubScores = $allClubs->map(function ($clubItem) use ($startDate, $endDate) {
            $members = $clubItem->joinedMembers;

            if ($members->isEmpty()) {
                return [
                    'club_id' => $clubItem->id,
                    'total_score' => 0,
                ];
            }

            $memberIds = $members->pluck('user_id')->filter()->unique();
            $histories = VnduprHistory::whereIn('user_id', $memberIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('user_id');

            $totalScore = 0;
            foreach ($members as $member) {
                $userId = $member->user_id;
                $userHistories = $histories->get($userId, collect());

                if ($userHistories->isNotEmpty()) {
                    $totalScore += $userHistories->last()->score_after;
                } else {
                    $vnduprScore = $member->user?->sports->flatMap(fn($sport) => $sport->scores()->get())
                        ->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')
                        ->first();
                    $totalScore += $vnduprScore ? $vnduprScore->score_value : 0;
                }
            }

            return [
                'club_id' => $clubItem->id,
                'total_score' => $totalScore,
            ];
        });

        $sortedClubs = $clubScores->sortByDesc('total_score')->values();

        $rank = null;
        foreach ($sortedClubs as $index => $item) {
            if ($item['club_id'] === $club->id) {
                $rank = $index + 1;
                break;
            }
        }

        return $rank;
    }

    /**
     * Bảng xếp hạng all-time của câu lạc bộ.
     */
    public function getLeaderboard(Club $club): Collection
    {
        $members = $club->joinedMembers()->with(['user.sports.scores'])->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $sport = Sport::where('slug', 'pickleball')->first();
        $sportId = $sport?->id ?? 1;

        $allHistories = VnduprHistory::whereIn('user_id', $members->pluck('user_id'))
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('user_id');

        $leaderboardData = $members->map(function ($member) use ($allHistories, $sportId) {
            return $this->calculateMemberStats($member, $allHistories, $sportId);
        });

        $sorted = $leaderboardData->sortByDesc('vndupr_score')->values();
        $verified = $sorted->filter(fn($item) => ($item['all_time_stats']['matches_played'] ?? 0) >= 10);
        $unverified = $sorted->filter(fn($item) => ($item['all_time_stats']['matches_played'] ?? 0) < 10);

        $topThree = $verified->take(3)->values();
        $rest = $verified->skip(3)
            ->concat($unverified)
            ->sortByDesc('vndupr_score')
            ->values();

        return $topThree
            ->concat($rest)
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });
    }

    private function calculateMemberStats(
        ClubMember $member,
        Collection $allHistories,
        int $sportId
    ): array {
        $userId = $member->user_id;
        $userHistories = $allHistories->get($userId, collect());

        $finalScore = 0;
        if ($userHistories->isNotEmpty()) {
            $finalScore = $userHistories->last()->score_after;
        } else {
            $vnduprScore = $member->user?->sports->flatMap(fn($sport) => $sport->scores()->get())
                ->where('score_type', 'vndupr_score')
                ->sortByDesc('created_at')
                ->first();
            $finalScore = $vnduprScore ? $vnduprScore->score_value : 0;
        }

        $stats = $this->calculateOverviewStats($userId, $sportId, $allHistories);

        return [
            'member_id' => $member->id,
            'user_id' => $userId,
            'user' => $member->user,
            'vndupr_score' => round($finalScore, 3),
            'all_time_stats' => $stats,
        ];
    }

    /**
     * Tính overview stats giống hệt matchesBySportId() trong UserMatchStatsController.
     * Bao gồm: tournament matches + mini tournament matches + quick matches.
     */
    private function calculateOverviewStats(int $userId, int $sportId, Collection $allHistories): array
    {
        // Tournament matches: user nằm trong team_members
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

        $tournamentMatchIds = $homeMatchIds->merge($awayMatchIds)->unique();

        // Mini tournament matches
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

        // Quick matches
        $quickMatchIds = MatchHistory::where('user_id', $userId)
            ->whereNotNull('quick_match_id')
            ->pluck('quick_match_id')
            ->unique();

        // Load dữ liệu cần thiết
        $matches = Matches::with([
                'homeTeam.members:id',
                'awayTeam.members:id',
                'tournamentType.tournament',
            ])
            ->whereIn('id', $tournamentMatchIds)
            ->get()
            ->filter(fn($m) => $m->tournamentType &&
                $m->tournamentType->tournament &&
                $m->tournamentType->tournament->sport_id == $sportId);

        $minis = MiniMatch::withFullRelations()
            ->whereIn('id', $miniIds)
            ->get()
            ->filter(fn($m) => $m->miniTournament &&
                $m->miniTournament->sport_id == $sportId);

        $quickMatches = QuickMatch::with('competitionLocation')
            ->where('status', QuickMatch::STATUS_COMPLETED)
            ->whereIn('id', $quickMatchIds)
            ->get()
            ->filter(fn($qm) => $qm->competitionLocation
                ? $qm->competitionLocation->sports->contains('id', $sportId)
                : true);

        $miniTeamMembersByTeam = collect();
        if ($minis->isNotEmpty()) {
            $miniTeamMembersByTeam = DB::table('mini_team_members')
                ->whereIn(
                    'mini_team_id',
                    $minis->pluck('team1_id')
                        ->merge($minis->pluck('team2_id'))
                        ->filter()
                        ->unique()
                )
                ->get()
                ->groupBy('mini_team_id')
                ->map(fn($rows) => $rows->pluck('user_id')->all());
        }

        $allTeamIds = $matches->pluck('home_team_id')
            ->concat($matches->pluck('away_team_id'))
            ->filter()
            ->unique();

        $teamMembersByTeam = collect();
        if ($allTeamIds->isNotEmpty()) {
            $membersData = DB::table('team_members')
                ->whereIn('team_id', $allTeamIds)
                ->get();
            $teamMembersByTeam = $membersData->groupBy('team_id')
                ->map(fn($rows) => $rows->pluck('user_id')->all());
        }

        $totalWin = 0;
        $totalLose = 0;

        // Tournament matches
        foreach ($matches as $match) {
            $homeMembers = $teamMembersByTeam[$match->home_team_id] ?? [];
            $awayMembers = $teamMembersByTeam[$match->away_team_id] ?? [];
            $homeUserIds = $homeMembers;
            $awayUserIds = $awayMembers;

            $userIsInHomeTeam = in_array($userId, $homeUserIds);
            $userIsInAwayTeam = in_array($userId, $awayUserIds);

            if (!$userIsInHomeTeam && !$userIsInAwayTeam) {
                continue;
            }

            $myTeamId = $userIsInHomeTeam ? $match->home_team_id : $match->away_team_id;
            $isWin = $match->winner_id == $myTeamId;

            if ($isWin) {
                $totalWin++;
            } else {
                $totalLose++;
            }
        }

        // Mini tournament matches
        foreach ($minis as $mini) {
            $t1Members = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
            $t2Members = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

            $userIsInTeam1 = in_array($userId, $t1Members);
            $userIsInTeam2 = in_array($userId, $t2Members);

            if (!$userIsInTeam1 && !$userIsInTeam2) {
                continue;
            }

            $myTeamId = $userIsInTeam1 ? $mini->team1_id : $mini->team2_id;
            $isWin = $mini->team_win_id == $myTeamId;

            if ($isWin) {
                $totalWin++;
            } else {
                $totalLose++;
            }
        }

        // Quick matches
        foreach ($quickMatches as $qm) {
            $isMyTeamA = in_array($userId, $qm->team_a ?? []);
            $teamSide = $isMyTeamA ? 'team_a' : 'team_b';
            $isWin = $qm->winner === $teamSide;

            if ($isWin) {
                $totalWin++;
            } else {
                $totalLose++;
            }
        }

        $totalMatches = $totalWin + $totalLose;
        $winRate = $totalMatches > 0 ? round(($totalWin / $totalMatches) * 100, 2) : 0;

        $userHistories = $allHistories->get($userId, collect());
        $firstHistory = $userHistories->sortBy('created_at')->first();
        $lastHistory = $userHistories->sortByDesc('created_at')->first();
        $scoreBefore = $firstHistory ? (float) $firstHistory->score_before : 0;
        $scoreAfter = $lastHistory ? (float) $lastHistory->score_after : 0;
        $scoreChange = round($scoreAfter - $scoreBefore, 3);

        return [
            'matches_played' => $totalMatches,
            'wins' => $totalWin,
            'losses' => $totalLose,
            'win_rate' => $winRate,
            'score_change' => $scoreChange,
        ];
    }
}
