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

        // Lấy VnduprHistory 365 ngày
        $histories = VnduprHistory::where('user_id', $userId)
            ->where('created_at', '>=', now()->subYear())
            ->orderBy('created_at', 'asc')
            ->get();

        if ($histories->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $matchIds = $histories->pluck('match_id')->filter()->unique();
        $miniIds  = $histories->pluck('mini_match_id')->filter()->unique();

        // ========== TỐI ƯU: FILTER BẰNG whereHas, CHỈ LOAD RELATIONS CẦN THIẾT ==========
        $matches = Matches::with([
                'homeTeam.members:id',
                'awayTeam.members:id',
                'tournamentType.tournament'
            ])
            ->whereIn('id', $matchIds)
            ->whereHas('tournamentType.tournament', fn($q) => $q->where('sport_id', $sportId))
            ->get()
            ->keyBy('id');

        $minis = MiniMatch::with([
                'team1.members:id',
                'team2.members:id',
                'miniTournament'
            ])
            ->whereIn('id', $miniIds)
            ->whereHas('miniTournament', fn($q) => $q->where('sport_id', $sportId))
            ->get()
            ->keyBy('id');

        // Lấy kết quả
        $matchResults = MatchResult::whereIn('match_id', $matches->keys())
            ->get()
            ->groupBy('match_id');

        $miniResults = MiniMatchResult::whereIn('mini_match_id', $minis->keys())
            ->get()
            ->groupBy('mini_match_id');

        // ========== CHỈ QUERY 1 LẦN CHO MINI_TEAM_MEMBERS ==========
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

        // Helper function để check win
        $checkWin = function ($history) use ($matches, $minis, $miniTeamMembersByTeam, $userId) {
            if ($history->match_id && $matches->has($history->match_id)) {
                $match = $matches[$history->match_id];

                // Lấy danh sách user_id từ members collection
                $homeUserIds = $match->homeTeam->members->pluck('id')->all();
                $awayUserIds = $match->awayTeam->members->pluck('id')->all();

                return (
                    ($match->winner_id == $match->home_team_id && in_array($userId, $homeUserIds)) ||
                    ($match->winner_id == $match->away_team_id && in_array($userId, $awayUserIds))
                );
            }

            if ($history->mini_match_id && $minis->has($history->mini_match_id)) {
                $mini = $minis[$history->mini_match_id];

                $isTeam1 = in_array($userId, $miniTeamMembersByTeam[$mini->team1_id] ?? []);
                $isTeam2 = in_array($userId, $miniTeamMembersByTeam[$mini->team2_id] ?? []);

                if ($isTeam1 && $mini->team_win_id == $mini->team1_id) return true;
                if ($isTeam2 && $mini->team_win_id == $mini->team2_id) return true;
            }

            return false;
        };

        // Helper function tính win_rate và performance
        $calculateStats = function ($historiesCollection) use ($checkWin) {
            // Sắp xếp theo thời gian mới nhất
            $sortedHistories = $historiesCollection->sortByDesc('created_at')->values();
            $totalMatches = $sortedHistories->count();

            if ($totalMatches == 0) {
                return ['win_rate' => 0, 'performance' => 0];
            }

            // Tính win_rate
            $winCount = 0;
            foreach ($sortedHistories as $match) {
                if ($checkWin($match)) {
                    $winCount++;
                }
            }
            $win_rate = round(($winCount / $totalMatches) * 100, 2);

            // Tính performance
            $points = 0;
            foreach ($sortedHistories as $index => $match) {
                if ($checkWin($match)) {
                    $multiplier = $index < 3 ? 1.5 : 1.0;
                    $points += 10 * $multiplier;
                }
            }

            $recent3Max = min(3, $totalMatches) * 10 * 1.5;
            $older7Max = max(0, $totalMatches - 3) * 10 * 1.0;
            $maxPoints = $recent3Max + $older7Max;

            $performance = $maxPoints > 0 ? round(($points / $maxPoints) * 100, 2) : 0;

            return ['win_rate' => $win_rate, 'performance' => $performance];
        };

        // ========== HELPER: REMOVE DUPLICATES ==========
        $removeDuplicates = function ($historiesCollection) {
            $unique = collect();
            $seen = [];

            foreach ($historiesCollection as $h) {
                $key = $h->match_id ? 'match_' . $h->match_id : 'mini_' . $h->mini_match_id;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $unique->push($h);
                }
            }
            return $unique;
        };

        // ========== HELPER: PROCESS WEEK DATA (CHI TIẾT SCORES) ==========
        $processWeekData = function ($historiesCollection) use ($matches, $minis, $matchResults, $miniResults, $miniTeamMembersByTeam, $userId) {
            $weekData = [];
            $weekLabels = [];

            foreach ($historiesCollection as $history) {
                if ($history->match_id && $matches->has($history->match_id)) {
                    $match = $matches->get($history->match_id);
                    $homeUserIds = $match->homeTeam->members->pluck('id')->all();
                    $awayUserIds = $match->awayTeam->members->pluck('id')->all();
                    $isHome = in_array($userId, $homeUserIds);
                    $myTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
                    $opponentTeamId = $isHome ? $match->away_team_id : $match->home_team_id;
                    $scores = [];
                    if ($matchResults->has($history->match_id)) {
                        $resultsBySet = $matchResults[$history->match_id]->groupBy('set_number');
                        foreach ($resultsBySet as $setNumber => $setResults) {
                            $my_set_score = 0;
                            $opponent_set_score = 0;
                            foreach ($setResults as $r) {
                                if ($r->team_id == $myTeamId) {
                                    $my_set_score += $r->score;
                                } elseif ($r->team_id == $opponentTeamId) {
                                    $opponent_set_score += $r->score;
                                }
                            }
                            $scores[] = [
                                'my_score' => $my_set_score,
                                'opponent_score' => $opponent_set_score
                            ];
                        }
                    }

                    $weekLabels[] = Carbon::parse($history->created_at)->toDateString();
                    $weekData[] = [
                        'scores' => $scores,
                        'is_win' => $match->winner_id == $myTeamId
                    ];
                } elseif ($history->mini_match_id && $minis->has($history->mini_match_id)) {
                    $mini = $minis->get($history->mini_match_id);

                    $team1Members = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
                    $team2Members = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

                    $isTeam1 = in_array($userId, $team1Members);
                    $myTeamId = $isTeam1 ? $mini->team1_id : $mini->team2_id;
                    $opponentTeamId = $isTeam1 ? $mini->team2_id : $mini->team1_id;
                    $scores = [];
                    if ($miniResults->has($history->mini_match_id)) {
                        $resultsBySet = $miniResults[$history->mini_match_id]->groupBy('set_number');

                        foreach ($resultsBySet as $setNumber => $setResults) {
                            $my_set_score = 0;
                            $opponent_set_score = 0;

                            foreach ($setResults as $r) {

                                // ✅ FIX: Dùng team_id thay vì team_id
                                if (isset($r->team_id)) {
                                    if ($r->team_id == $myTeamId) {
                                        $my_set_score += $r->score;
                                    } elseif ($r->team_id == $opponentTeamId) {
                                        $opponent_set_score += $r->score;
                                    }
                                }
                            }

                            $scores[] = [
                                'my_score' => $my_set_score,
                                'opponent_score' => $opponent_set_score
                            ];
                        }
                    }

                    $weekLabels[] = Carbon::parse($history->created_at)->toDateString();
                    $weekData[] = [
                        'scores' => $scores,
                        'is_win' => $mini->team_win_id == $myTeamId
                    ];
                }
            }

            return ['labels' => $weekLabels, 'data' => $weekData];
        };

        // ========== HELPER: CALCULATE WIN RATE BY GROUP ==========
        $calculateWinRateByGroup = function ($historiesCollection, $groupByFormat) use ($checkWin) {
            $groups = [];
            foreach ($historiesCollection as $h) {
                $groupKey = Carbon::parse($h->created_at)->format($groupByFormat);
                $matchKey = $h->match_id ? 'match_' . $h->match_id : 'mini_' . $h->mini_match_id;

                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [];
                }
                if (!isset($groups[$groupKey][$matchKey])) {
                    $groups[$groupKey][$matchKey] = $h;
                }
            }

            $result = [];
            foreach ($groups as $groupKey => $items) {
                $winCount = 0;
                $totalCount = count($items);

                foreach ($items as $h) {
                    if ($checkWin($h)) $winCount++;
                }

                $result[$groupKey] = $totalCount > 0 ? round(($winCount / $totalCount) * 100, 2) : 0;
            }

            return $result;
        };

        $chart = [];

        // ========== 1. WEEK ==========
        $weekHistories = $histories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(7)));
        $uniqueWeek = $removeDuplicates($weekHistories);
        $weekStats = $calculateStats($uniqueWeek);
        $weekResult = $processWeekData($uniqueWeek);

        $chart['week'] = [
            'labels' => $weekResult['labels'],
            'datasets' => $weekResult['data'],
            'win_rate' => $weekStats['win_rate'],
            'performance' => $weekStats['performance']
        ];

        // ========== 2. 30 DAYS ==========
        $monthHistories = $histories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(30)));
        $uniqueMonth = $removeDuplicates($monthHistories);
        $monthStats = $calculateStats($uniqueMonth);
        $monthData = $calculateWinRateByGroup($monthHistories, 'Y-m-d');

        $chart['30days'] = [
            'labels' => array_map(fn($date) => Carbon::parse($date)->format('d/m'), array_keys($monthData)),
            'datasets' => array_values($monthData),
            'win_rate' => $monthStats['win_rate'],
            'performance' => $monthStats['performance']
        ];

        // ========== 3. 90 DAYS ==========
        $quarterHistories = $histories->filter(fn($h) => Carbon::parse($h->created_at)->gte(now()->subDays(90)));
        $uniqueQuarter = $removeDuplicates($quarterHistories);
        $quarterStats = $calculateStats($uniqueQuarter);
        $quarterData = $calculateWinRateByGroup($quarterHistories, 'Y-W');

        $chart['90days'] = [
            'labels' => array_map(function ($week) {
                $parts = explode('-', $week);
                return ltrim($parts[1], '0') . '/' . $parts[0];
            }, array_keys($quarterData)),
            'datasets' => array_values($quarterData),
            'win_rate' => $quarterStats['win_rate'],
            'performance' => $quarterStats['performance']
        ];

        // ========== 4. 365 DAYS ==========
        $uniqueYear = $removeDuplicates($histories);
        $yearStats = $calculateStats($uniqueYear);
        $yearData = $calculateWinRateByGroup($histories, 'Y-m');

        $chart['365days'] = [
            'labels' => array_map(function ($month) {
                $parts = explode('-', $month);
                return $parts[1] . '/' . $parts[0];
            }, array_keys($yearData)),
            'datasets' => array_values($yearData),
            'win_rate' => $yearStats['win_rate'],
            'performance' => $yearStats['performance']
        ];

        return ResponseHelper::success($chart, 'Lấy dữ liệu thành công');
    }

    public function matchesBySportId(Request $request)
    {
        $userId = $request->query('user_id', auth()->id());
        $sportId = $request->query('sport_id');
        $perPage = $request->query('per_page', 15); // Mặc định 15 items/page

        if (!$sportId) {
            return ResponseHelper::error('Có lỗi xảy ra trong quá trình thực thi', 400);
        }

        // Lấy tất cả VnduprHistory của user
        $histories = VnduprHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($histories->isEmpty()) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1
            ]);
        }

        $matchIds = $histories->pluck('match_id')->filter()->unique();
        $miniIds = $histories->pluck('mini_match_id')->filter()->unique();

        // Lấy Matches với filter sport_id
        $matches = Matches::withFullRelations()
            ->whereIn('id', $matchIds)
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
            $history = $histories->where('match_id', $match->id)->first();
            if (!$history) continue;

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
                        if ($r->team_id == $myTeamId) {
                            $myScore += $r->score;
                        } elseif ($r->team_id == $opponentTeamId) {
                            $opponentScore += $r->score;
                        }
                    }

                    $scores[] = [
                        'my_score' => $myScore,
                        'opponent_score' => $opponentScore,
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
                'created_at' => $history->created_at
            ]);
        }

        // ========== XỬ LÝ MINI MATCHES - TEAM-BASED, SWAP ĐỂ USER LUÔN Ở TEAM1 ==========
        // Hydrate miniTournamentParticipant vào mỗi member TRƯỚC vòng lặp (1 lần duy nhất)
        if ($minis->isNotEmpty()) {
            $miniTournamentId = $minis->first()->miniTournament->id;
            $teamsToHydrate = $minis->pluck('team1')->merge($minis->pluck('team2'))->filter()->unique('id');
            foreach ($teamsToHydrate as $team) {
                MiniTournamentTeamMemberHydrator::hydrateTeam($team, (int) $miniTournamentId);
            }
        }

        foreach ($minis as $mini) {
            $history = $histories->where('mini_match_id', $mini->id)->first();
            if (!$history) continue;

            $team1Members = $miniTeamMembersByTeam[$mini->team1_id] ?? [];
            $team2Members = $miniTeamMembersByTeam[$mini->team2_id] ?? [];

            $team1UserIds = array_column($team1Members, 'user_id');
            $team2UserIds = array_column($team2Members, 'user_id');

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
                        if ($r->team_id == $myTeamId) {
                            $myScore += $r->score;
                        } elseif ($r->team_id == $opponentTeamId) {
                            $opponentScore += $r->score;
                        }
                    }

                    $scores[] = [
                        'my_score' => $myScore,
                        'opponent_score' => $opponentScore,
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
                'created_at' => $history->created_at
            ]);
        }

        // Sort theo created_at giảm dần
        $allMatches = $allMatches->sortByDesc('created_at')->values();

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
