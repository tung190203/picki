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
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use App\Services\Club\ClubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __construct(protected ClubService $clubService) {}

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

        $userSports = UserSport::where('user_id', $userId)
            ->with('sport', 'scores', 'user')
            ->get();

        // Load sport stats on the auth user for UserSportResource
        $user->setRelation('sports', $userSports);
        User::loadSportStatsOnUsers(collect([$user]), 1);

        $primarySportStats = $user->preloaded_sport_stats ?? [
            'win_rate' => 0.0,
            'performance' => 0,
        ];

        $userInfo = [
            'win_rate'    => $primarySportStats['win_rate'] ?? 0.0,
            'performance' => $primarySportStats['performance'] ?? 0,
            'sports'      => UserSportResource::collection($userSports),
            'is_anchor' => (bool) $user->is_anchor,
            'is_verify' => (bool) ($user->total_matches_has_anchor >= 10),
        ];
        $nowVN = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
    
        // Lấy upcoming mini tournaments
        $upcomingMiniTournaments = MiniTournament::withFullRelations()
            ->whereDate('start_time', '>=', $nowVN)
            ->where(function ($query) use ($userId) {
                $query->whereHas('participants', fn($p) => $p->where('user_id', $userId)->whereNull('declined_at'))
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
            ->where(function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('created_by', $userId)
                      ->whereIn('status', [Tournament::DRAFT, Tournament::OPEN]);
                })->orWhere(function ($q) use ($userId) {
                    $q->where('created_by', '!=', $userId)
                      ->where('status', Tournament::OPEN);
                });
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

        if ($myClub->isNotEmpty()) {
            $this->clubService->attachUnreadNotificationCount($myClub, $userId);
        }

        // Leaderboard club - CACHED (1 giờ)
        $leaderboardClubPerPage = $validated['leaderboard_club_per_page'] ?? Club::PER_PAGE;
        $leaderboardClubCacheKey = 'leaderboard_club:top:' . $leaderboardClubPerPage;

        $leaderboardClub = Cache::remember($leaderboardClubCacheKey, 3600, function () use ($leaderboardClubPerPage) {
            return Club::allClubs()
                ->with(['members.user'])
                ->get()
                ->map(function ($club) {
                    $maxScore = $club->members
                        ->map(fn($member) => $member->user?->vnduprScores?->max('score_value') ?? 0)
                        ->max() ?? 0;
                    $club->cached_max_vndupr_score = $maxScore;
                    return $club;
                })
                ->sortByDesc('cached_max_vndupr_score')
                ->take($leaderboardClubPerPage);
        });

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
            ->where('users.total_matches_has_anchor', '>', 5)
            ->where('users.email', '!=', 'vrplus2018@gmail.com')
            ->joinSub($scoreSubQuery, 'scores', function ($join) {
                $join->on('scores.user_id', '=', 'users.id');
            })
            ->with([
                'sports' => function ($query) use ($sportId) {
                    $query->where('sport_id', $sportId)
                        ->with('scores', 'sport', 'user.scoreVerificationRequests');
                },
                'clubs:id,name'
            ])
            ->select(
                'users.*',
                'scores.vndupr_score',
                DB::raw('ROW_NUMBER() OVER (ORDER BY scores.vndupr_score DESC) as rank')
            )
            ->orderByDesc('scores.vndupr_score')
            ->limit($perPage)
            ->get();

        User::loadSportStatsOnUsers($leaderboard, $sportId);

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
                    'clubs' => $user->clubs->map(function($club) {
                        return [
                            'id' => $club->id,
                            'name' => $club->name
                        ];
                    }),
                    'is_anchor' => (bool) $user->is_anchor,
                    'is_verify' => (bool) ($user->total_matches_has_anchor >= 10)
                ];
            }),
        ];
    
        return ResponseHelper::success($data, 'Lấy dữ liệu thành công');
    }
}
