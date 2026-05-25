<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\MiniTeamResource;
use App\Http\Resources\TeamResource;
use App\Support\MiniTournamentTeamMemberHydrator;
use App\Support\TournamentTeamMemberHydrator;
use Illuminate\Http\Request;
use App\Models\VnduprHistory;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MatchResult;
use App\Models\MiniMatchResult;
use App\Models\MatchHistory;
use App\Models\QuickMatch;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserMatchStatsController extends Controller
{

    public static function getSportStats(int $userId, int $sportId): array
    {
        $matchCount = (int) DB::table('vndupr_history')
            ->join('matches', 'vndupr_history.match_id', '=', 'matches.id')
            ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
            ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
            ->where('vndupr_history.user_id', $userId)
            ->where('tournaments.sport_id', $sportId)
            ->whereNotNull('vndupr_history.match_id')
            ->selectRaw('COUNT(DISTINCT vndupr_history.match_id) as cnt')
            ->value('cnt');

        $miniMatchCount = (int) DB::table('vndupr_history')
            ->join('mini_matches', 'vndupr_history.mini_match_id', '=', 'mini_matches.id')
            ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
            ->where('vndupr_history.user_id', $userId)
            ->where('mini_tournaments.sport_id', $sportId)
            ->whereNotNull('vndupr_history.mini_match_id')
            ->selectRaw('COUNT(DISTINCT vndupr_history.mini_match_id) as cnt')
            ->value('cnt');

        $matchesPlayed = $matchCount + $miniMatchCount;

        if ($matchesPlayed === 0) {
            return [
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => 0,
                'score_change' => 0,
            ];
        }

        $matchWins = (int) DB::table('vndupr_history')
            ->join('matches', 'vndupr_history.match_id', '=', 'matches.id')
            ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
            ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
            ->join('team_members', function ($join) use ($userId) {
                $join->on('team_members.team_id', '=', 'matches.winner_id')
                    ->where('team_members.user_id', '=', $userId);
            })
            ->where('vndupr_history.user_id', $userId)
            ->where('tournaments.sport_id', $sportId)
            ->whereNotNull('vndupr_history.match_id')
            ->whereNotNull('matches.winner_id')
            ->selectRaw('COUNT(DISTINCT vndupr_history.match_id) as cnt')
            ->value('cnt');

        $miniMatchWins = (int) DB::table('vndupr_history')
            ->join('mini_matches', 'vndupr_history.mini_match_id', '=', 'mini_matches.id')
            ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
            ->join('mini_team_members', function ($join) use ($userId) {
                $join->on('mini_team_members.mini_team_id', '=', 'mini_matches.team_win_id')
                    ->where('mini_team_members.user_id', '=', $userId);
            })
            ->where('vndupr_history.user_id', $userId)
            ->where('mini_tournaments.sport_id', $sportId)
            ->whereNotNull('vndupr_history.mini_match_id')
            ->whereNotNull('mini_matches.team_win_id')
            ->selectRaw('COUNT(DISTINCT vndupr_history.mini_match_id) as cnt')
            ->value('cnt');

        $wins = $matchWins + $miniMatchWins;
        $losses = $matchesPlayed - $wins;
        $winRate = round(($wins / $matchesPlayed) * 100, 2);

        $firstHistory = DB::table('vndupr_history')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->first();
        $lastHistory = DB::table('vndupr_history')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        $scoreBefore = $firstHistory ? (float) $firstHistory->score_before : 0;
        $scoreAfter = $lastHistory ? (float) $lastHistory->score_after : 0;
        $scoreChange = round($scoreAfter - $scoreBefore, 3);

        return [
            'matches_played' => $matchesPlayed,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'score_change' => $scoreChange,
        ];
    }

    public function dataset(Request $request)
    {
        $userId = $request->query('user_id', auth()->id());
        $sportId = $request->query('sport_id');

        if (!$sportId) {
            return ResponseHelper::error('Có lỗi xảy ra trong quá trình thực thi', 400);
        }

        $since = now()->subYear();

        // ========== 1. TOURNAMENT MATCHES ==========
        // team_members → teams → matches (home/away) → tournament_types → tournaments
        $teamIds = DB::table('team_members')->where('user_id', $userId)->pluck('team_id');

        $matchIds = DB::table('matches')
            ->whereIn('home_team_id', $teamIds)
            ->orWhereIn('away_team_id', $teamIds)
            ->whereNotNull('winner_id')
            ->where('updated_at', '>=', $since)
            ->pluck('id')
            ->unique();

        $matches = collect();
        if ($matchIds->isNotEmpty()) {
            $matches = Matches::with([
                    'homeTeam.members:id',
                    'awayTeam.members:id',
                    'tournamentType.tournament',
                ])
                ->whereIn('id', $matchIds)
                ->whereHas('tournamentType.tournament', fn($q) => $q->where('sport_id', $sportId))
                ->get()
                ->keyBy('id');
        }

        $matchResults = MatchResult::whereIn('match_id', $matches->keys())
            ->get()
            ->groupBy('match_id');

        // ========== 2. MINI TOURNAMENT MATCHES ==========
        // 2a. Đánh đơn: user nằm trong mini_participants (is_confirmed, user_id direct)
        $singleTournamentIds = DB::table('mini_participants')
            ->where('user_id', $userId)
            ->where('is_confirmed', 1)
            ->whereNotNull('mini_tournament_id')
            ->pluck('mini_tournament_id')
            ->unique();

        // 2b. Đánh đôi/team: user nằm trong mini_team_members
        $teamMatchTournamentIds = DB::table('mini_team_members')
            ->join('mini_teams', 'mini_team_members.mini_team_id', '=', 'mini_teams.id')
            ->where('mini_team_members.user_id', $userId)
            ->pluck('mini_teams.mini_tournament_id')
            ->unique();

        $allMiniTournamentIds = $singleTournamentIds->merge($teamMatchTournamentIds)->unique();

        $miniMatchIds = collect();
        if ($allMiniTournamentIds->isNotEmpty()) {
            $miniMatchIds = DB::table('mini_matches')
                ->whereIn('mini_tournament_id', $allMiniTournamentIds)
                ->whereNotNull('team_win_id')
                ->where('updated_at', '>=', $since)
                ->pluck('id');
        }

        $minis = collect();
        if ($miniMatchIds->isNotEmpty()) {
            $minis = MiniMatch::with([
                    'team1.members:id',
                    'team2.members:id',
                    'miniTournament',
                ])
                ->whereIn('id', $miniMatchIds)
                ->whereHas('miniTournament', fn($q) => $q->where('sport_id', $sportId))
                ->get()
                ->keyBy('id');
        }

        $miniResults = MiniMatchResult::whereIn('mini_match_id', $minis->keys())
            ->get()
            ->groupBy('mini_match_id');

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

        // ========== 3. QUICK MATCHES ==========
        $matchHistories = MatchHistory::where('user_id', $userId)
            ->where('played_at', '>=', $since)
            ->whereNotNull('quick_match_id')
            ->get()
            ->keyBy('quick_match_id');

        $quickMatchIds = $matchHistories->pluck('quick_match_id')->unique();

        $quickMatches = collect();
        if ($quickMatchIds->isNotEmpty()) {
            $quickMatches = QuickMatch::with('competitionLocation')
                ->whereIn('id', $quickMatchIds)
                ->where('status', QuickMatch::STATUS_COMPLETED)
                ->get()
                ->filter(fn($qm) => $qm->competitionLocation
                    ? $qm->competitionLocation->sports->contains('id', $sportId)
                    : true)
                ->keyBy('id');
        }

        // ========== BUILD UNIFIED HISTORIES ==========
        $allHistories = collect();

        foreach ($matches as $match) {
            $allHistories->push((object) [
                'type'         => 'match',
                'id'           => $match->id,
                'created_at'    => $match->updated_at,
                'match_id'     => $match->id,
                'mini_match_id'  => null,
                'quick_match_id' => null,
            ]);
        }

        foreach ($minis as $mini) {
            $allHistories->push((object) [
                'type'          => 'mini_match',
                'id'            => $mini->id,
                'created_at'    => $mini->updated_at,
                'match_id'      => null,
                'mini_match_id' => $mini->id,
                'quick_match_id'=> null,
            ]);
        }

        foreach ($matchHistories as $history) {
            if (!$quickMatches->has($history->quick_match_id)) continue;
            $allHistories->push((object) [
                'type'          => 'quick_match',
                'id'            => $history->quick_match_id,
                'created_at'    => $history->played_at,
                'match_id'      => null,
                'mini_match_id' => null,
                'quick_match_id'=> $history->quick_match_id,
            ]);
        }

        $allHistories = $allHistories->sortBy('created_at')->values();

        if ($allHistories->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // ========== HELPER: CHECK WIN ==========
        $checkWin = function ($item) use ($matches, $minis, $quickMatches, $miniTeamMembersByTeam, $userId) {
            if ($item->type === 'match' && $matches->has($item->match_id)) {
                $match = $matches[$item->match_id];
                $homeUserIds = $match->homeTeam->members->pluck('id')->all();
                $awayUserIds = $match->awayTeam->members->pluck('id')->all();
                return (
                    ($match->winner_id == $match->home_team_id && in_array($userId, $homeUserIds)) ||
                    ($match->winner_id == $match->away_team_id && in_array($userId, $awayUserIds))
                );
            }

            if ($item->type === 'mini_match' && $minis->has($item->mini_match_id)) {
                $mini = $minis[$item->mini_match_id];
                $isTeam1 = in_array($userId, $miniTeamMembersByTeam[$mini->team1_id] ?? []);
                $isTeam2 = in_array($userId, $miniTeamMembersByTeam[$mini->team2_id] ?? []);
                if ($isTeam1 && $mini->team_win_id == $mini->team1_id) return true;
                if ($isTeam2 && $mini->team_win_id == $mini->team2_id) return true;
                return false;
            }

            if ($item->type === 'quick_match' && $quickMatches->has($item->quick_match_id)) {
                $qm = $quickMatches[$item->quick_match_id];
                if (!$qm->winner) return false;
                $userInTeamA = in_array($userId, $qm->team_a ?? []);
                $userInTeamB = in_array($userId, $qm->team_b ?? []);
                return ($qm->winner === QuickMatch::WINNER_TEAM_A && $userInTeamA)
                    || ($qm->winner === QuickMatch::WINNER_TEAM_B && $userInTeamB);
            }

            return false;
        };

        // ========== HELPER: REMOVE DUPLICATES ==========
        $removeDuplicates = function ($collection) {
            $unique = collect();
            $seen = [];
            foreach ($collection as $item) {
                $key = $item->type . '_' . $item->id;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique->push($item);
                }
            }
            return $unique;
        };

        // ========== HELPER: PROCESS WEEK DATA ==========
        $processWeekData = function ($collection) use ($matches, $minis, $quickMatches, $matchResults, $miniResults, $miniTeamMembersByTeam, $userId) {
            $weekData = [];
            $weekLabels = [];

            foreach ($collection as $item) {
                $scores = [];
                $isWin = false;

                if ($item->type === 'match' && $matches->has($item->match_id)) {
                    $match = $matches->get($item->match_id);
                    $homeUserIds = $match->homeTeam->members->pluck('id')->all();
                    $awayUserIds = $match->awayTeam->members->pluck('id')->all();
                    $isHome = in_array($userId, $homeUserIds);
                    $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
                    $opponentTeamId = $isHome ? $match->away_team_id : $match->home_team_id;

                    if ($matchResults->has($item->match_id)) {
                        foreach ($matchResults[$item->match_id]->groupBy('set_number') as $setResults) {
                            $myScore = 0;
                            $oppScore = 0;
                            foreach ($setResults as $r) {
                                if ($r->team_id == $myTeamId)        $myScore += $r->score;
                                elseif ($r->team_id == $opponentTeamId) $oppScore += $r->score;
                            }
                            $scores[] = ['my_score' => (int) $myScore, 'opponent_score' => (int) $oppScore];
                        }
                    }
                    $isWin = $match->winner_id == $myTeamId;

                } elseif ($item->type === 'mini_match' && $minis->has($item->mini_match_id)) {
                    $mini = $minis->get($item->mini_match_id);
                    $t1Members = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
                    $t2Members = $miniTeamMembersByTeam[$mini->team2_id] ?? [];
                    $isTeam1 = in_array($userId, $t1Members);
                    $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
                    $opponentTeamId = $isTeam1 ? $mini->team2_id : $mini->team1_id;

                    if ($miniResults->has($item->mini_match_id)) {
                        foreach ($miniResults[$item->mini_match_id]->groupBy('set_number') as $setResults) {
                            $myScore = 0;
                            $oppScore = 0;
                            foreach ($setResults as $r) {
                                if ($r->team_id !== null && $r->team_id > 0) {
                                    if ($r->team_id == $myTeamId)        $myScore += $r->score;
                                    elseif ($r->team_id == $opponentTeamId) $oppScore += $r->score;
                                }
                            }
                            $scores[] = ['my_score' => (int) $myScore, 'opponent_score' => (int) $oppScore];
                        }
                    }
                    $isWin = $mini->team_win_id == $myTeamId;

                } elseif ($item->type === 'quick_match' && $quickMatches->has($item->quick_match_id)) {
                    $qm = $quickMatches->get($item->quick_match_id);
                    $userInTeamA = in_array($userId, $qm->team_a ?? []);
                    $teamAScores = $qm->score['team_a'] ?? [];
                    $teamBScores = $qm->score['team_b'] ?? [];
                    $maxSets = max(count($teamAScores), count($teamBScores));

                    for ($i = 0; $i < $maxSets; $i++) {
                        $myScore = $userInTeamA ? ($teamAScores[$i] ?? 0) : ($teamBScores[$i] ?? 0);
                        $oppScore = $userInTeamA ? ($teamBScores[$i] ?? 0) : ($teamAScores[$i] ?? 0);
                        $scores[] = ['my_score' => (int) $myScore, 'opponent_score' => (int) $oppScore];
                    }

                    if ($qm->winner) {
                        $isWin = ($qm->winner === QuickMatch::WINNER_TEAM_A && $userInTeamA)
                            || ($qm->winner === QuickMatch::WINNER_TEAM_B && !$userInTeamA);
                    }
                }

                $weekLabels[] = Carbon::parse($item->created_at)->toDateString();
                $weekData[] = ['scores' => $scores, 'is_win' => $isWin];
            }

            return ['labels' => $weekLabels, 'data' => $weekData];
        };

        // ========== HELPER: WIN RATE BY GROUP ==========
        $calculateWinRateByGroup = function ($collection, $groupByFormat) use ($checkWin) {
            $groups = [];
            foreach ($collection as $item) {
                $groupKey = Carbon::parse($item->created_at)->format($groupByFormat);
                $matchKey = $item->type . '_' . $item->id;
                if (!isset($groups[$groupKey])) $groups[$groupKey] = [];
                if (!isset($groups[$groupKey][$matchKey])) $groups[$groupKey][$matchKey] = $item;
            }

            $result = [];
            foreach ($groups as $groupKey => $items) {
                $winCount = 0;
                foreach ($items as $item) {
                    if ($checkWin($item)) $winCount++;
                }
                $total = count($items);
                $result[$groupKey] = $total > 0 ? round(($winCount / $total) * 100, 2) : 0;
            }
            return $result;
        };

        $chart = [];

        // ========== 1. WEEK ==========
        $weekHistories = $allHistories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(7)));
        $uniqueWeek = $removeDuplicates($weekHistories);
        $chart['week'] = [
            'labels'      => $processWeekData($uniqueWeek)['labels'],
            'datasets'    => $processWeekData($uniqueWeek)['data'],
            'win_rate'    => $this->calculateStats($uniqueWeek, $checkWin)['win_rate'],
            'performance' => $this->calculateStats($uniqueWeek, $checkWin)['performance'],
        ];

        // ========== 2. 30 DAYS ==========
        $monthHistories = $allHistories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(30)));
        $uniqueMonth = $removeDuplicates($monthHistories);
        $monthData = $calculateWinRateByGroup($monthHistories, 'Y-m-d');
        $chart['30days'] = [
            'labels'      => array_map(fn($d) => Carbon::parse($d)->format('d/m'), array_keys($monthData)),
            'datasets'    => array_values($monthData),
            'win_rate'    => $this->calculateStats($uniqueMonth, $checkWin)['win_rate'],
            'performance' => $this->calculateStats($uniqueMonth, $checkWin)['performance'],
        ];

        // ========== 3. 90 DAYS ==========
        $quarterHistories = $allHistories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(90)));
        $uniqueQuarter = $removeDuplicates($quarterHistories);
        $quarterData = $calculateWinRateByGroup($quarterHistories, 'Y-W');
        $chart['90days'] = [
            'labels'      => array_map(fn($w) => ltrim(explode('-', $w)[1], '0') . '/' . explode('-', $w)[0], array_keys($quarterData)),
            'datasets'    => array_values($quarterData),
            'win_rate'    => $this->calculateStats($uniqueQuarter, $checkWin)['win_rate'],
            'performance' => $this->calculateStats($uniqueQuarter, $checkWin)['performance'],
        ];

        // ========== 4. 365 DAYS ==========
        $uniqueYear = $removeDuplicates($allHistories);
        $yearData = $calculateWinRateByGroup($allHistories, 'Y-m');
        $chart['365days'] = [
            'labels'      => array_map(fn($m) => explode('-', $m)[1] . '/' . explode('-', $m)[0], array_keys($yearData)),
            'datasets'    => array_values($yearData),
            'win_rate'    => $this->calculateStats($uniqueYear, $checkWin)['win_rate'],
            'performance' => $this->calculateStats($uniqueYear, $checkWin)['performance'],
        ];

        return ResponseHelper::success($chart, 'Lấy dữ liệu thành công');
    }

    private function calculateStats($uniqueCollection, $checkWin): array
    {
        $sorted = $uniqueCollection->sortBy(fn($h) => $h->played_at ?? $h->created_at, SORT_REGULAR, true)->values();
        $total = $sorted->count();
        if ($total == 0) return ['win_rate' => 0, 'performance' => 0];

        $winCount = $sorted->filter(fn($h) => $checkWin($h))->count();
        $winRate = round(($winCount / $total) * 100, 2);

        $points = 0;
        foreach ($sorted as $i => $item) {
            if ($checkWin($item)) {
                $points += 10 * ($i < 3 ? 1.5 : 1.0);
            }
        }
        $recent3Max = min(3, $total) * 10 * 1.5;
        $olderMax = max(0, $total - 3) * 10 * 1.0;
        $performance = ($recent3Max + $olderMax) > 0 ? round(($points / ($recent3Max + $olderMax)) * 100, 2) : 0;

        return ['win_rate' => $winRate, 'performance' => $performance];
    }

    public function matchesBySportId(Request $request)
    {
        $userId = $request->query('user_id', auth()->id());
        $sportId = 1; // Luôn luôn dùng sport_id = 1
        $perPage = $request->query('per_page', 15);

        // Lấy tournament match IDs trực tiếp từ team_members + matches + tournaments
        // Tách thành 2 query (home và away) rồi merge
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

        // Lấy mini match IDs từ mini_team_members
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

        // Lấy Matches với filter sport_id
        $matches = Matches::withFullRelations()
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

        // Lấy kết quả
        $matchResults = MatchResult::whereIn('match_id', $matches->pluck('id'))
            ->get()
            ->groupBy('match_id');

        $miniResults = MiniMatchResult::whereIn('mini_match_id', $minis->pluck('id'))
            ->get()
            ->groupBy('mini_match_id');

        // ========== TEAM MEMBERS CHO MINI MATCHES (TEAM-BASED) ==========
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
            ->map(fn($rows) => $rows->keyBy('user_id')->map(fn($row) => [
                'user_id'  => $row->user_id,
                'is_guest' => (bool) $row->is_guest,
            ])->values()->all());

        // Lấy team members
        $allTeamIds = $matches->pluck('home_team_id')
            ->concat($matches->pluck('away_team_id'))
            ->filter()
            ->unique();

        $teamMembersByTeam = collect();
        if ($allTeamIds->isNotEmpty()) {
            $members = DB::table('team_members')
                ->join('users', 'team_members.user_id', '=', 'users.id')
                ->whereIn('team_id', $allTeamIds)
                ->select('team_members.team_id', 'team_members.user_id', 'users.is_guest')
                ->get();
            $teamMembersByTeam = $members->groupBy('team_id')
                ->map(fn($rows) => $rows->keyBy('user_id')->map(fn($row) => [
                    'user_id'  => $row->user_id,
                    'is_guest' => (bool) $row->is_guest,
                ])->values()->all());
        }

        // Merge matches và mini matches với thông tin đầy đủ
        $allMatches = collect();

        // Xử lý Matches
        foreach ($matches as $match) {
            // Lấy members từ các team
            $homeMembers = $teamMembersByTeam[$match->home_team_id] ?? [];
            $awayMembers = $teamMembersByTeam[$match->away_team_id] ?? [];

            $homeUserIds = array_column($homeMembers, 'user_id');
            $awayUserIds = array_column($awayMembers, 'user_id');

            // Xác định user thuộc team nào
            $userIsInHomeTeam = in_array($userId, $homeUserIds);
            $userIsInAwayTeam = in_array($userId, $awayUserIds);

            // Bỏ qua nếu user không thuộc team nào (edge case)
            if (!$userIsInHomeTeam && !$userIsInAwayTeam) {
                continue;
            }

            // SWAP: User luôn ở vị trí "my_team" (home position)
            if ($userIsInHomeTeam) {
                // User đã ở home team rồi, không cần swap
                $myTeam = $match->homeTeam;
                $opponentTeam = $match->awayTeam;
                $myTeamId = $match->home_team_id;
                $opponentTeamId = $match->away_team_id;
            } else {
                // User ở away team, swap để đưa lên home
                $myTeam = $match->awayTeam;
                $opponentTeam = $match->homeTeam;
                $myTeamId = $match->away_team_id;
                $opponentTeamId = $match->home_team_id;
            }

            // Tính điểm số theo set
            $scores = [];
            $is_win = false;

            if ($matchResults->has($match->id)) {
                $resultsBySet = $matchResults[$match->id]->groupBy('set_number');

                foreach ($resultsBySet as $setNumber => $setResults) {
                    $myScore = 0;
                    $opponentScore = 0;

                    foreach ($setResults as $r) {
                        if ($r->team_id !== null && $r->team_id > 0) {
                            if ($r->team_id == $myTeamId) {
                                $myScore += $r->score;
                            } elseif ($r->team_id == $opponentTeamId) {
                                $opponentScore += $r->score;
                            }
                        }
                    }

                    $scores[] = [
                        'my_score' => (int) $myScore,
                        'opponent_score' => (int) $opponentScore,
                        'set_number' => $setNumber
                    ];
                }

                // ✅ is_win dựa vào myTeamId (đã swap)
                $is_win = ($match->winner_id == $myTeamId);
            }

            $allMatches->push([
                'type' => 'match',
                'format' => 'team',
                'id' => $match->id,
                'tournament_id' => $match->tournamentType->tournament->id ?? null,
                'tournament_name' => $match->tournamentType->tournament->name ?? null,
                'match_name' => $match->name_of_match,
                'my_team' => $this->teamResourceWithTournamentHydrated(
                    $myTeam,
                    $match->tournamentType->tournament->id ?? null
                ),
                'opponent_team' => $this->teamResourceWithTournamentHydrated(
                    $opponentTeam,
                    $match->tournamentType->tournament->id ?? null
                ),
                'my_team_id' => $myTeamId,
                'opponent_team_id' => $opponentTeamId,
                'scores' => $scores,
                'is_win' => $is_win,
                'status' => $match->status,
                'match_date' => $match->match_date,
                'created_at' => $match->updated_at
            ]);
        }

        // ========== XỬ LÝ MINI MATCHES - GROUP THEO MINI_TOURNAMENT ĐỂ HYDRATE ĐÚNG ==========
        if ($minis->isNotEmpty()) {
            // Group matches theo mini_tournament_id để hydrate đúng participant
            $byTournament = $minis->groupBy(fn($m) => $m->mini_tournament_id);
            foreach ($byTournament as $miniTournamentId => $tournamentMatches) {
                $teamsToHydrate = $tournamentMatches
                    ->map(fn($m) => $m->team1)
                    ->merge($tournamentMatches->map(fn($m) => $m->team2))
                    ->filter()
                    ->unique('id');
                foreach ($teamsToHydrate as $team) {
                    MiniTournamentTeamMemberHydrator::hydrateTeam($team, (int) $miniTournamentId);
                }
            }
        }

        foreach ($minis as $mini) {
            $team1Members = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
            $team2Members = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

            $team1UserIds = array_column($team1Members, 'user_id');
            $team2UserIds = array_column($team2Members, 'user_id');

            if ($mini->team1_id === null || $mini->team2_id === null) {
                continue;
            }

            // Xác định user thuộc team nào
            $userIsInTeam1 = in_array($userId, $team1UserIds);
            $userIsInTeam2 = in_array($userId, $team2UserIds);

            // Bỏ qua nếu user không thuộc team nào
            if (!$userIsInTeam1 && !$userIsInTeam2) {
                continue;
            }

            // SWAP: User luôn ở vị trí "my_team" (team1/home position)
            if ($userIsInTeam1) {
                // User đã ở team1, không cần swap
                $myTeam = $mini->team1;
                $opponentTeam = $mini->team2;
                $myTeamId = $mini->team1_id;
                $opponentTeamId = $mini->team2_id;
            } else {
                // User ở team2, swap để đưa lên team1
                $myTeam = $mini->team2;
                $opponentTeam = $mini->team1;
                $myTeamId = $mini->team2_id;
                $opponentTeamId = $mini->team1_id;
            }

            // Tính điểm số theo set
            $scores = [];
            $is_win = false;

            if ($miniResults->has($mini->id)) {
                $resultsBySet = $miniResults[$mini->id]->groupBy('set_number');

                foreach ($resultsBySet as $setNumber => $setResults) {
                    $myScore = 0;
                    $opponentScore = 0;

                    foreach ($setResults as $r) {
                        if ($r->team_id !== null && $r->team_id > 0) {
                            if ($r->team_id == $myTeamId) {
                                $myScore += $r->score;
                            } elseif ($r->team_id == $opponentTeamId) {
                                $opponentScore += $r->score;
                            }
                        }
                    }

                    $scores[] = [
                        'my_score' => (int) $myScore,
                        'opponent_score' => (int) $opponentScore,
                        'set_number' => $setNumber
                    ];
                }

                $is_win = ($mini->team_win_id == $myTeamId);
            }

            $allMatches->push([
                'type' => 'mini_match',
                'format' => 'team',
                'id' => $mini->id,
                'mini_tournament_id' => $mini->miniTournament->id ?? null,
                'mini_tournament_name' => $mini->miniTournament->name ?? null,
                'match_name' => $mini->name,
                'my_team' => (new MiniTeamResource($myTeam))->forMiniTournament($mini->miniTournament->id ?? null),
                'opponent_team' => (new MiniTeamResource($opponentTeam))->forMiniTournament($mini->miniTournament->id ?? null),
                'my_team_id' => $myTeamId,
                'opponent_team_id' => $opponentTeamId,
                'scores' => $scores,
                'is_win' => $is_win,
                'status' => $mini->status,
                'match_date' => $mini->match_date,
                'created_at' => $mini->updated_at
            ]);
        }

        // Sort theo created_at giảm dần
        $allMatches = $allMatches->sortByDesc('created_at')->values();

        // ========== QUICK MATCHES ==========
        $quickMatchHistories = \App\Models\MatchHistory::where('user_id', $userId)
            ->whereNotNull('quick_match_id')
            ->get()
            ->keyBy('quick_match_id');
        $quickMatchIds = $quickMatchHistories->pluck('quick_match_id')->unique();

        $quickMatches = \App\Models\QuickMatch::with('competitionLocation')
            ->where('status', \App\Models\QuickMatch::STATUS_COMPLETED)
            ->whereIn('id', $quickMatchIds)
            ->get()
            ->filter(fn($qm) => $qm->competitionLocation
                ? $qm->competitionLocation->sports->contains('id', $sportId)
                : true);

        if ($quickMatches->isNotEmpty()) {
            $qmUserIds = $quickMatches
                ->flatMap(fn($qm) => array_merge($qm->team_a ?? [], $qm->team_b ?? []))
                ->unique()
                ->values();

            $qmUsers = \App\Models\User::whereIn('id', $qmUserIds)->get()->keyBy('id');

            foreach ($quickMatches as $qm) {
                $history = $quickMatchHistories[$qm->id] ?? null;
                $playedAt = $history ? $history->played_at : $qm->updated_at;
                $isMyTeamA = in_array($userId, $qm->team_a ?? []);
                $teamAUserIds = $qm->team_a ?? [];
                $teamBUserIds = $qm->team_b ?? [];

                $teamAUsers = collect($teamAUserIds)->map(fn($id) => $qmUsers[$id] ?? null)->filter()->values();
                $teamBUsers = collect($teamBUserIds)->map(fn($id) => $qmUsers[$id] ?? null)->filter()->values();

                if ($isMyTeamA) {
                    $myTeamUsers = $teamAUsers;
                    $oppTeamUsers = $teamBUsers;
                    $myTeamName = 'Team A';
                    $oppTeamName = 'Team B';
                } else {
                    $myTeamUsers = $teamBUsers;
                    $oppTeamUsers = $teamAUsers;
                    $myTeamName = 'Team B';
                    $oppTeamName = 'Team A';
                }

                $scores = [];
                $teamAScores = $qm->score['team_a'] ?? [];
                $teamBScores = $qm->score['team_b'] ?? [];
                $maxSets = max(count($teamAScores), count($teamBScores));

                for ($i = 0; $i < $maxSets; $i++) {
                    if ($isMyTeamA) {
                        $scores[] = [
                            'my_score' => (int) ($teamAScores[$i] ?? 0),
                            'opponent_score' => (int) ($teamBScores[$i] ?? 0),
                            'set_number' => $i + 1,
                        ];
                    } else {
                        $scores[] = [
                            'my_score' => (int) ($teamBScores[$i] ?? 0),
                            'opponent_score' => (int) ($teamAScores[$i] ?? 0),
                            'set_number' => $i + 1,
                        ];
                    }
                }

                $teamSide = $isMyTeamA ? 'team_a' : 'team_b';

                $allMatches->push([
                    'type' => 'quick_match',
                    'format' => 'quick',
                    'id' => $qm->id,
                    'match_name' => $qm->name,
                    'team_side' => $teamSide,
                    'my_team' => [
                        'id' => null,
                        'name' => $myTeamName,
                        'members' => $myTeamUsers->map(fn($u) => [
                            'id' => $u->id,
                            'team_id' => null,
                            'full_name' => $u->full_name,
                            'avatar_url' => $u->avatar_url,
                            'is_guest' => (bool) ($u->is_guest ?? false),
                            'visibility' => $u->visibility ?? 'open',
                            'user' => [
                                'id' => $u->id,
                                'full_name' => $u->full_name,
                                'avatar_url' => $u->avatar_url,
                                'visibility' => $u->visibility ?? 'open',
                                'sports' => [],
                            ],
                        ])->values()->all(),
                    ],
                    'opponent_team' => [
                        'id' => null,
                        'name' => $oppTeamName,
                        'members' => $oppTeamUsers->map(fn($u) => [
                            'id' => $u->id,
                            'team_id' => null,
                            'full_name' => $u->full_name,
                            'avatar_url' => $u->avatar_url,
                            'is_guest' => (bool) ($u->is_guest ?? false),
                            'visibility' => $u->visibility ?? 'open',
                            'user' => [
                                'id' => $u->id,
                                'full_name' => $u->full_name,
                                'avatar_url' => $u->avatar_url,
                                'visibility' => $u->visibility ?? 'open',
                                'sports' => [],
                            ],
                        ])->values()->all(),
                    ],
                    'my_team_id' => null,
                    'opponent_team_id' => null,
                    'scores' => $scores,
                    'is_win' => $qm->winner === $teamSide,
                    'status' => $qm->status,
                    'match_date' => $playedAt,
                    'created_at' => $playedAt,
                ]);
            }

            // Sort lại sau khi thêm quick matches
            $allMatches = $allMatches->sortByDesc('created_at')->values();
        }

        // Phân trang thủ công
        $total = $allMatches->count();
        $lastPage = ceil($total / $perPage);
        $currentPage = max(1, min($request->query('page', 1), $lastPage));

        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = $allMatches->slice($offset, $perPage)->values();

        $matches = [
            'matches' => $paginatedData
        ];

        $meta = [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total
        ];

        return ResponseHelper::success($matches, 'Lấy danh sách trận đấu thành công', 200,  $meta);
    }

    /**
     * Hydrate members (participant + sports) rồi bọc TeamResource.
     * Không dùng TeamResource::forTournament() để API chạy được cả khi server chưa deploy method đó.
     */
    private function teamResourceWithTournamentHydrated($team, ?int $tournamentId): TeamResource
    {
        if ($team && $tournamentId) {
            $team->loadMissing(['members.sports.scores', 'members.sports.sport']);
            TournamentTeamMemberHydrator::hydrateTeam($team, (int) $tournamentId);
        }

        return new TeamResource($team);
    }
}
