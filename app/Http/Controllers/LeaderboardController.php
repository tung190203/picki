<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TeamLeaderboardResource;
use App\Models\Club\Club;
use App\Models\Participant;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\UserSportScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LeaderboardController extends Controller
{
    public function index(Request $request, ?int $tournamentId = null)
    {
        $validated = $request->validate([
            'per_page'       => 'sometimes|integer|min:1|max:200',
            'sport_id'       => 'sometimes|integer|exists:sports,id',
            'tournament_id'  => 'sometimes|integer|exists:tournaments,id',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $tournamentId = $tournamentId ?? ($validated['tournament_id'] ?? null);

        if (!$tournamentId) {
            return ResponseHelper::error('tournament_id là bắt buộc.', 422);
        }

        $sportId = $validated['sport_id'] ?? null;
        if (!$sportId) {
            $sport = Sport::where('slug', 'pickleball')->first();
            if (!$sport) {
                return ResponseHelper::error('Sport không tồn tại.', 404);
            }
            $sportId = $sport->id;
        }

        $tournamentTypeIds = TournamentType::where('tournament_id', $tournamentId)->pluck('id');
        if ($tournamentTypeIds->isEmpty()) {
            return ResponseHelper::success(['leaderboard' => []], 'Không tìm thấy giải đấu.');
        }

        $rankings = TeamRanking::with(['team.members'])
            ->whereIn('tournament_type_id', $tournamentTypeIds)
            ->get();

        if ($rankings->isEmpty()) {
            return ResponseHelper::success(['leaderboard' => []], 'Không có dữ liệu xếp hạng.');
        }

        $teamStats = $this->getTeamStats(
            $rankings->pluck('team_id')->unique(),
            $sportId,
            $tournamentId
        );

        // Preload participant records cho tất cả members thuộc các team trong leaderboard
        $allMemberIds = $rankings
            ->pluck('team.members')
            ->flatten()
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        $participants = Participant::where('tournament_id', $tournamentId)
            ->whereIn('user_id', $allMemberIds)
            ->get()
            ->keyBy('user_id');

        $currentUserId = Auth::id();

        $leaderboard = $rankings
            ->groupBy('team_id')
            ->map(function ($teamRankings, $teamId) use ($teamStats, $sportId, $participants, $currentUserId) {
                $firstRanking = $teamRankings->first();
                $team = $firstRanking->team;
                $stats = $teamStats[$teamId] ?? ['total_matches' => 0, 'win_rate' => 0, 'vndupr_avg' => 0];

                $rank = $this->resolveFinalRank($teamRankings);

                $tournamentTypes = $teamRankings->map(fn($r) => [
                    'id'   => $r->tournamentType->id,
                    'name' => $r->tournamentType->format_label ?? 'N/A',
                ])->values()->all();

                $memberUserIds = $team->members->pluck('id')->toArray();
                $isMyTeam = $currentUserId && in_array($currentUserId, $memberUserIds);

                $members = $team->members->map(function ($m) use ($participants) {
                    $participant = $participants->get($m->id);
                    return [
                        'id'            => $m->id,
                        'full_name'     => $m->full_name,
                        'avatar_url'    => $m->avatar_url,
                        'participant'   => $participant ? [
                            'id'                  => $participant->id,
                            'is_confirmed'        => (bool) $participant->is_confirmed,
                            'is_guest'            => (bool) $participant->is_guest,
                            'is_pending_confirmation' => (bool) $participant->is_pending_confirmation,
                            'is_absent'           => (bool) $participant->is_absent,
                            'guarantor_user_id'   => $participant->guarantor_user_id,
                            'checked_in_at'       => $participant->checked_in_at?->toIsoString(),
                        ] : null,
                    ];
                })->values()->all();

                return new TeamLeaderboardResource([
                    'id'            => $team->id,
                    'name'          => $team->name,
                    'avatar'        => $team->avatar,
                    'vndupr_avg'    => $stats['vndupr_avg'],
                    'members'       => $members,
                    'tournament_types' => $tournamentTypes,
                    'is_my_team'    => $isMyTeam,
                ], $rank, $stats['total_matches'], $stats['win_rate']);
            })
            ->sortBy(fn($item) => $item->rank)
            ->values()
            ->take($perPage);

        return ResponseHelper::success([
            'leaderboard' => $leaderboard->values(),
        ], 'Lấy dữ liệu leaderboard thành công');
    }

    private function getTeamStats($teamIds, int $sportId, int $tournamentId): array
    {
        $ttIds = TournamentType::where('tournament_id', $tournamentId)->pluck('id');

        $matches = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->whereIn('tt.tournament_id', [$tournamentId])
            ->whereIn('m.home_team_id', $teamIds)
            ->where('m.status', 'completed')
            ->whereNotNull('m.winner_id')
            ->select('m.home_team_id as team_id', 'm.winner_id')
            ->unionAll(
                DB::table('matches as m')
                    ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
                    ->whereIn('tt.tournament_id', [$tournamentId])
                    ->whereIn('m.away_team_id', $teamIds)
                    ->where('m.status', 'completed')
                    ->whereNotNull('m.winner_id')
                    ->select('m.away_team_id as team_id', 'm.winner_id')
            )
            ->get();

        $stats = [];
        foreach ($teamIds as $teamId) {
            $teamMatches = $matches->filter(fn($m) => $teamId == $m->team_id);
            $total = $teamMatches->count();
            $wins = $teamMatches->filter(fn($m) => $m->winner_id == $teamId)->count();
            $stats[$teamId] = [
                'total_matches' => $total,
                'win_rate'      => $total > 0 ? round(($wins / $total) * 100, 2) : 0,
                'vndupr_avg'    => 0,
            ];
        }

        $this->loadVnduprAvg($stats, $teamIds, $sportId);

        return $stats;
    }

    private function resolveFinalRank($teamRankings): int
    {
        $rankingsByType = $teamRankings->groupBy(fn($r) => $r->tournamentType->format);

        if (isset($rankingsByType[TournamentType::FORMAT_MIXED]) && $rankingsByType[TournamentType::FORMAT_MIXED]->count() > 1) {
            return $rankingsByType[TournamentType::FORMAT_MIXED]->max('rank');
        }

        return $teamRankings->min('rank');
    }

    private function loadVnduprAvg(array &$stats, $teamIds, int $sportId): void
    {
        $teams = Team::with(['members.sports' => function ($q) use ($sportId) {
            $q->where('sport_id', $sportId);
        }])->whereIn('id', $teamIds)->get();

        foreach ($teams as $team) {
            $scores = [];
            foreach ($team->members as $member) {
                foreach ($member->sports as $userSport) {
                    $latest = $userSport->scores
                        ->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')
                        ->first();
                    if ($latest) {
                        $scores[] = (float) $latest->score_value;
                    }
                }
            }
            if (isset($stats[$team->id])) {
                $stats[$team->id]['vndupr_avg'] = !empty($scores)
                    ? round(array_sum($scores) / count($scores), 3) : 0.0;
            }
        }
    }

    public function getLeaderboard(Request $request)
    {
        $validated = $request->validate([
            'scope'    => 'required|in:all,club,friend',
            'club_id'  => 'required_if:scope,club|integer|exists:clubs,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page'     => 'sometimes|integer|min:1',
        ]);

        $scope = $validated['scope'];
        $perPage = $validated['per_page'] ?? 50;
        $page = $validated['page'] ?? 1;

        $sport = Sport::where('slug', 'pickleball')->first();
        $sportId = $sport?->id ?? 1;

        if ($scope === 'all') {
            $leaderboardData = $this->getSystemLeaderboard($sportId, $perPage, $page);

            return ResponseHelper::success([
                'scope'       => $scope,
                'leaderboard' => $leaderboardData['items'],
                'meta'        => [
                    'total'     => $leaderboardData['total'],
                    'per_page'  => $perPage,
                    'page'      => $page,
                    'last_page' => $leaderboardData['last_page'],
                ],
            ], 'Lấy bảng xếp hạng thành công');
        }

        $leaderboardData = match ($scope) {
            'club'   => $this->getClubLeaderboard((int) $validated['club_id'], $sportId, $perPage, $page),
            'friend' => $this->getFriendsLeaderboard($sportId, $perPage, $page),
        };

        return ResponseHelper::success([
            'scope'       => $scope,
            'leaderboard' => $leaderboardData['items'],
            'meta'        => [
                'total'     => $leaderboardData['total'],
                'per_page'  => $perPage,
                'page'      => $page,
                'last_page' => $leaderboardData['last_page'],
            ],
        ], 'Lấy bảng xếp hạng thành công');
    }

    private function getSystemLeaderboard(int $sportId, int $perPage, int $page): array
    {
        $scoreSubQuery = UserSportScore::query()
            ->select(
                'user_sport.user_id',
                DB::raw('MAX(user_sport_scores.score_value) as vndupr_score')
            )
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->groupBy('user_sport.user_id');

        $baseQuery = User::query()
            ->where('users.total_matches', '>', 5)
            ->where('users.email', '!=', 'vrplus2018@gmail.com')
            ->joinSub($scoreSubQuery, 'scores', function ($join) {
                $join->on('scores.user_id', '=', 'users.id');
            })
            ->with(['clubs:id,name'])
            ->select(
                'users.id',
                'users.full_name',
                'users.visibility',
                'users.avatar_url',
                'users.is_anchor',
                'users.is_verified',
                'scores.vndupr_score',
                DB::raw('RANK() OVER (ORDER BY scores.vndupr_score DESC) as rank')
            )
            ->orderByDesc('scores.vndupr_score');

        $total = $baseQuery->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $leaderboard = $baseQuery->offset($offset)->limit($perPage)->get();

        $items = $leaderboard->map(function ($user) {
            return [
                'id'         => $user->id,
                'full_name'  => $user->full_name,
                'visibility' => $user->visibility,
                'avatar_url'  => $user->avatar_url,
                'rank'       => (int) $user->rank,
                'vndupr_score' => round((float) $user->vndupr_score, 3),
                'clubs'      => $user->clubs->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
                'is_anchor'  => (bool) $user->is_anchor,
                'is_verify'  => (bool) ($user->is_verified ?? false),
            ];
        });

        return [
            'items'    => $items,
            'total'    => $total,
            'last_page' => $lastPage,
        ];
    }

    private function getClubLeaderboard(int $clubId, int $sportId, int $perPage, int $page): array
    {
        $club = Club::findOrFail($clubId);
        $memberIds = $club->joinedMembers()->pluck('user_id')->filter()->unique();

        if ($memberIds->isEmpty()) {
            return ['items' => [], 'total' => 0, 'last_page' => 1];
        }

        $scoreSubQuery = UserSportScore::query()
            ->select(
                'user_sport.user_id',
                DB::raw('MAX(user_sport_scores.score_value) as vndupr_score')
            )
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->whereIn('user_sport.user_id', $memberIds)
            ->groupBy('user_sport.user_id');

        $baseQuery = User::query()
            ->joinSub($scoreSubQuery, 'scores', function ($join) {
                $join->on('scores.user_id', '=', 'users.id');
            })
            ->with(['clubs:id,name'])
            ->select(
                'users.id',
                'users.full_name',
                'users.visibility',
                'users.avatar_url',
                'users.is_anchor',
                'users.is_verified',
                'scores.vndupr_score'
            )
            ->orderByDesc('scores.vndupr_score');

        $total = $baseQuery->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $leaderboard = $baseQuery->offset($offset)->limit($perPage)->get();

        $items = $leaderboard->map(function ($user, $index) use ($page, $perPage) {
            return [
                'id'         => $user->id,
                'full_name'  => $user->full_name,
                'visibility' => $user->visibility,
                'avatar_url'  => $user->avatar_url,
                'rank'       => ($page - 1) * $perPage + $index + 1,
                'vndupr_score' => round((float) $user->vndupr_score, 3),
                'clubs'      => $user->clubs->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
                'is_anchor'  => (bool) $user->is_anchor,
                'is_verify'  => (bool) ($user->is_verified ?? false),
            ];
        });

        return [
            'items'    => $items,
            'total'    => $total,
            'last_page' => $lastPage,
        ];
    }

    private function getFriendsLeaderboard(int $sportId, int $perPage, int $page): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['items' => [], 'total' => 0, 'last_page' => 1];
        }

        $friendIds = $user->friends()->pluck('users.id');
        if ($friendIds->isEmpty()) {
            return ['items' => [], 'total' => 0, 'last_page' => 1];
        }

        $scoreSubQuery = UserSportScore::query()
            ->select(
                'user_sport.user_id',
                DB::raw('MAX(user_sport_scores.score_value) as vndupr_score')
            )
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->whereIn('user_sport.user_id', $friendIds)
            ->groupBy('user_sport.user_id');

        $baseQuery = User::query()
            ->joinSub($scoreSubQuery, 'scores', function ($join) {
                $join->on('scores.user_id', '=', 'users.id');
            })
            ->whereIn('users.id', $friendIds)
            ->with(['clubs:id,name'])
            ->select(
                'users.id',
                'users.full_name',
                'users.visibility',
                'users.avatar_url',
                'users.is_anchor',
                'users.is_verified',
                'scores.vndupr_score'
            )
            ->orderByDesc('scores.vndupr_score');

        $total = $baseQuery->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $leaderboard = $baseQuery->offset($offset)->limit($perPage)->get();

        $items = $leaderboard->map(function ($user, $index) use ($page, $perPage) {
            return [
                'id'         => $user->id,
                'full_name'  => $user->full_name,
                'visibility' => $user->visibility,
                'avatar_url'  => $user->avatar_url,
                'rank'       => ($page - 1) * $perPage + $index + 1,
                'vndupr_score' => round((float) $user->vndupr_score, 3),
                'clubs'      => $user->clubs->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
                'is_anchor'  => (bool) $user->is_anchor,
                'is_verify'  => (bool) ($user->is_verified ?? false),
            ];
        });

        return [
            'items'    => $items,
            'total'    => $total,
            'last_page' => $lastPage,
        ];
    }
}
