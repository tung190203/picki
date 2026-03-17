<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\BannerResource;
use App\Http\Resources\ListClubResource;
use App\Http\Resources\ListMiniTournamentResource;
use App\Http\Resources\ListTournamentResource;
use App\Http\Resources\UserSportResource;
use App\Models\Banner;
use App\Models\Club\Club;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserSportScore;
use App\Models\VnduprHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'mini_tournament_per_page' => 'sometimes|integer|min:1|max:200',
            'tournament_per_page'      => 'sometimes|integer|min:1|max:200',
            'banner_per_page'          => 'sometimes|integer|min:1|max:200',
            'club_per_page'            => 'sometimes|integer|min:1|max:200',
            'leaderboard_club_per_page'     => 'sometimes|integer|min:1|max:200',
            'leaderboard_per_page' => 'sometimes|integer|min:1|max:200',
        ]);
    
        $user = auth()->user();
        $userId = $user->id;
    
        $sport = Sport::where('slug', 'pickleball')->first();
        if (!$sport) {
            return ResponseHelper::error('Sport không tồn tại.', 404);
        }
        // Lấy 10 trận gần nhất
        $histories = VnduprHistory::where('user_id', $userId)
            ->latest()
            ->take(10)
            ->get();
    
        // ========== REMOVE DUPLICATES ==========
        $uniqueHistories = collect();
        $seen = [];
        foreach ($histories as $h) {
            $key = $h->match_id ? 'match_' . $h->match_id : 'mini_' . $h->mini_match_id;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueHistories->push($h);
            }
        }
    
        $totalMatches = $uniqueHistories->count();
        $wins = 0;
        $totalPoint = 0;
    
        if ($totalMatches > 0) {
            $matchIds = $uniqueHistories->pluck('match_id')->filter()->unique()->values()->all();
            $miniIds  = $uniqueHistories->pluck('mini_match_id')->filter()->unique()->values()->all();
    
            $matches = Matches::whereIn('id', $matchIds)->get()->keyBy('id');
            $minis = MiniMatch::withFullRelations()->whereIn('id', $miniIds)->get()->keyBy('id');
    
            $teamIds = $matches->pluck('winner_id')->filter()->unique()->values()->all();
            $teamMembersByTeam = collect();
            if (!empty($teamIds)) {
                $members = DB::table('team_members')
                    ->whereIn('team_id', $teamIds)
                    ->get();
                $teamMembersByTeam = $members->groupBy('team_id')
                    ->map(fn($rows) => $rows->pluck('user_id')->flip());
            }
    
            // ========== MINI TEAM MEMBERS ==========
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
                ->map(fn($rows) => $rows->pluck('user_id')->flip());
    
            foreach ($uniqueHistories->values() as $index => $history) {
                $isWin = false;
    
                // match_id: winner là team
                if ($history->match_id) {
                    $match = $matches->get($history->match_id);
                    if ($match && $match->winner_id) {
                        $teamMembers = $teamMembersByTeam->get($match->winner_id);
                        $isWin = $teamMembers ? $teamMembers->has($userId) : false;
                    }
                }
                // mini_match_id: winner là user trực tiếp
                elseif ($history->mini_match_id) {
                    $mini = $minis->get($history->mini_match_id);
                    if ($mini && $mini->team_win_id) {
                        $winningTeamMembers = $miniTeamMembersByTeam->get($mini->team_win_id);
                        $isWin = $winningTeamMembers ? $winningTeamMembers->has($userId) : false;
                    }
                }
    
                if ($isWin) {
                    $wins++;
                    $coef = $index < 3 ? 1.5 : 1.0;
                    $totalPoint += 10 * $coef;
                }
            }
        }
    
        // Tính win_rate
        $winRate = $totalMatches > 0 ? ($wins / $totalMatches) * 100 : 0;
    
        // Tính performance
        $maxPoint = 0;
        for ($i = 0; $i < $totalMatches; $i++) {
            $maxPoint += $i < 3 ? 15 : 10;
        }
        $performance = $maxPoint > 0 ? ($totalPoint / $maxPoint) * 100 : 0;

        // ==========================
        // B. FIX SCORE (CHUẨN leaderboard)
        // ==========================

        $vnduprScore = UserSportScore::query()
            ->join('user_sport', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->where('user_sport.user_id', $userId)
            ->where('user_sport.sport_id', $sport->id)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->max('score_value') ?? 0;

        $duprScore = UserSportScore::query()
            ->join('user_sport', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->where('user_sport.user_id', $userId)
            ->where('user_sport.sport_id', $sport->id)
            ->where('user_sport_scores.score_type', 'dupr_score')
            ->max('score_value') ?? 0;

        $personalScore = UserSportScore::query()
            ->join('user_sport', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->where('user_sport.user_id', $userId)
            ->where('user_sport.sport_id', $sport->id)
            ->where('user_sport_scores.score_type', 'personal_score')
            ->max('score_value') ?? 0;

        $scores = [
            'personal_score' => number_format($personalScore, 3),
            'dupr_score' => number_format($duprScore, 3),
            'vndupr_score' => number_format($vnduprScore, 3),
        ];
        // A. Matches
        $matchCount = DB::table('vndupr_history')
            ->join('matches', 'vndupr_history.match_id', '=', 'matches.id')
            ->join('tournament_types', 'matches.tournament_type_id', '=', 'tournament_types.id')
            ->join('tournaments', 'tournament_types.tournament_id', '=', 'tournaments.id')
            ->where('vndupr_history.user_id', $userId)
            ->where('tournaments.sport_id', $sport->id)
            ->count();

        // B. Mini matches
        $miniMatchCount = DB::table('vndupr_history')
            ->join('mini_matches', 'vndupr_history.mini_match_id', '=', 'mini_matches.id')
            ->join('mini_tournaments', 'mini_matches.mini_tournament_id', '=', 'mini_tournaments.id')
            ->where('vndupr_history.user_id', $userId)
            ->where('mini_tournaments.sport_id', $sport->id)
            ->count();
    
        $totalMatchesAll = $matchCount + $miniMatchCount;
    
        $matchIds = DB::table('vndupr_history')
            ->where('user_id', $userId)
            ->whereNotNull('match_id')
            ->pluck('match_id')
            ->toArray();

        $tournamentsCount = 0;
        if (!empty($matchIds)) {
            $tournamentsCount = DB::table('matches as m')
                ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
                ->join('tournaments as t', 'tt.tournament_id', '=', 't.id')
                ->whereIn('m.id', $matchIds)
                ->where('t.sport_id', $sport->id)
                ->distinct()
                ->count('t.id');
        }

        $sports = [
            [
                'sport_id'   => $sport->id,
                'sport_icon' => $sport->icon,
                'sport_name' => $sport->name,
                'scores' => $scores,
                'total_matches' => $totalMatchesAll,
                'total_tournament' => $tournamentsCount,
                'total_prizes' => 0
            ]
        ];
        // User info
        $userInfo = [
            'win_rate'    => round($winRate, 2),
            'performance' => round($performance, 2),
            'sports'      => $sports,
            'is_anchor' => (bool) $user->is_anchor,
            'is_verify' => (bool) ($user->total_matches_has_anchor >= 10),
        ];
        $nowVN = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
    
        // Lấy upcoming mini tournaments
        $upcomingMiniTournaments = MiniTournament::withFullRelations()
            ->whereDate('start_time', '>=', $nowVN)
            ->where(function ($query) use ($userId) {
                $query->whereHas('participants', fn($p) => $p->where('user_id', $userId))
                      ->orWhereHas('staff', fn($s) => $s->where('user_id', $userId));
            })
            ->orderBy('start_time', 'asc')
            ->take($validated['mini_tournament_per_page'] ?? MiniTournament::PER_PAGE)
            ->get();
    
        // Lấy upcoming tournaments
        $upcomingTournaments = Tournament::withFullRelations()
            ->whereDate('start_date', '>=', $nowVN)
            ->where(function ($query) use ($userId) {
                $query->whereHas('participants', fn($p) => $p->where('user_id', $userId))
                      ->orWhereHas('tournamentStaffs', fn($s) => $s->where('user_id', $userId));
            })
            ->orderBy('start_date', 'asc')
            ->take($validated['tournament_per_page'] ?? Tournament::PER_PAGE)
            ->get();
    
        // Banners
        $banners = Banner::where('is_active', true)
            ->orderBy('order', 'asc')
            ->take($validated['banner_per_page'] ?? Banner::PER_PAGE)
            ->get();
    
        // My club
        $myClub = Club::with(['members.user.vnduprScores'])
            ->whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->take($validated['club_per_page'] ?? Club::PER_PAGE)
            ->get();
    
        // Leaderboard club
        $leaderboardClub = Club::with(['members.user.vnduprScores'])
            ->get()
            ->map(function ($club) {
                $club->members_max_vndupr_score = $club->members
                    ->map(fn($member) => $member->user?->vnduprScores?->max('score_value') ?? 0)
                    ->max();
                return $club;
            })
            ->sortByDesc('members_max_vndupr_score')
            ->take($validated['leaderboard_club_per_page'] ?? Club::PER_PAGE);

        // Leaderboard
        $sportId = $sport->id;
        $perPage = $validated['leaderboard_per_page'] ?? User::PER_PAGE;

        $scoreSubQuery = UserSportScore::query()
            ->select(
                'user_sport.user_id',
                DB::raw('MAX(user_sport_scores.score_value) as vndupr_score')
            )
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->groupBy('user_sport.user_id');

        $leaderboard = User::query()
            ->where('users.total_matches', '>', 5)
            ->where('users.email', '!=', 'vrplus2018@gmail.com')
            ->joinSub($scoreSubQuery, 'scores', function ($join) {
                $join->on('scores.user_id', '=', 'users.id');
            })
            ->withFullRelations()
            ->with([
                'sports' => function ($query) use ($sportId) {
                    $query->where('sport_id', $sportId)
                        ->with('scores', 'sport');
                },
            ])
            ->select(
                'users.*',
                'scores.vndupr_score',
                DB::raw('RANK() OVER (ORDER BY scores.vndupr_score DESC) as rank')
            )
            ->orderByDesc('scores.vndupr_score')
            ->limit($perPage)
            ->get();

        // Trả về data
        $data = [
            'user_info'              => $userInfo,
            'upcoming_mini_tournament' => ListMiniTournamentResource::collection($upcomingMiniTournaments),
            'upcoming_tournaments'     => ListTournamentResource::collection($upcomingTournaments),
            'banners'                   => BannerResource::collection($banners),
            'my_club'                   => ListClubResource::collection($myClub),
            'leaderboard_club'               => ListClubResource::collection($leaderboardClub),
            'leaderboard' => $leaderboard->map(function($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'visibility' => $user->visibility,
                    'avatar_url' => $user->avatar_url,
                    'rank' => $user->rank,
                    'sports' => $user->relationLoaded('sports') && $user->sports ? UserSportResource::collection($user->sports) : [],
                    'is_anchor' => (bool) $user->is_anchor,
                    'is_verify' => (bool) ($user->total_matches_has_anchor >= 10)
                ];
            }),
        ];
    
        return ResponseHelper::success($data, 'Lấy dữ liệu thành công');
    }
}