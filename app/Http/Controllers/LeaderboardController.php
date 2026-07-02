<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TeamLeaderboardResource;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Participant;
use App\Models\Sport;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Models\Tournament;
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
            return ResponseHelper::success(['leaderboard' => [], 'is_final' => false], 'Không tìm thấy giải đấu.');
        }

        $tournament = Tournament::find($tournamentId);
        $isFinal = $tournament && $tournament->status === Tournament::CLOSED;

        $rankings = TeamRanking::with(['team.members'])
            ->whereIn('tournament_type_id', $tournamentTypeIds)
            ->get();

        if ($rankings->isEmpty()) {
            return ResponseHelper::success(['leaderboard' => [], 'is_final' => $isFinal], 'Không có dữ liệu xếp hạng.');
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
                $stats = $teamStats[$teamId] ?? ['total_matches' => 0, 'win_rate' => 0, 'vndupr_avg' => 0, 'last_round' => null];
                $lastRound = $stats['last_round'] ?? null;

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
                ], $rank, $stats['total_matches'], $stats['win_rate'], $lastRound);
            })
            ->sortBy(fn($item) => $item->rank)
            ->values()
            ->take($perPage);

        return ResponseHelper::success([
            'leaderboard' => $leaderboard->values(),
            'is_final'   => $isFinal,
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
            ->select('m.home_team_id as team_id', 'm.winner_id', 'm.round')
            ->unionAll(
                DB::table('matches as m')
                    ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
                    ->whereIn('tt.tournament_id', [$tournamentId])
                    ->whereIn('m.away_team_id', $teamIds)
                    ->where('m.status', 'completed')
                    ->whereNotNull('m.winner_id')
                    ->select('m.away_team_id as team_id', 'm.winner_id', 'm.round')
            );

        $statsRaw = DB::table(DB::raw("({$matches->toSql()}) as combined"))
            ->mergeBindings($matches)
            ->select('team_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN winner_id = team_id THEN 1 ELSE 0 END) as wins')
            ->selectRaw('MAX(round) as last_round')
            ->groupBy('team_id')
            ->get();

        $stats = [];
        foreach ($teamIds as $teamId) {
            $row = $statsRaw->first(fn($r) => $r->team_id == $teamId);
            $stats[$teamId] = [
                'total_matches' => (int) ($row->total ?? 0),
                'win_rate'      => $row && $row->total > 0 ? round(($row->wins / $row->total) * 100, 2) : 0,
                'vndupr_avg'    => 0,
                'last_round'    => $row->last_round ?? null,
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
        if (empty($stats)) return;

        $teams = Team::with(['members.sports' => function ($q) use ($sportId) {
            $q->where('sport_id', $sportId);
        }])->whereIn('id', $teamIds)->get();

        $allUserSportIds = collect();
        $memberToSports = [];

        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                foreach ($member->sports as $us) {
                    $allUserSportIds->push($us->id);
                    $memberToSports[$member->id][] = $us->id;
                }
            }
        }

        if ($allUserSportIds->isEmpty()) return;

        $scoreMap = UserSportScore::whereIn('user_sport_id', $allUserSportIds)
            ->where('score_type', UserSportScore::VNDUPR_SCORE)
            ->get()
            ->groupBy('user_sport_id')
            ->map(fn($col) => $col->sortByDesc('created_at')->first());

        foreach ($teams as $team) {
            $scores = [];
            foreach ($team->members as $member) {
                $sportIds = $memberToSports[$member->id] ?? [];
                foreach ($sportIds as $usId) {
                    $latest = $scoreMap->get($usId);
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
            'scope'    => 'required|in:all,club,friend,allClubs',
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

        if ($scope === 'allClubs') {
            $leaderboardData = $this->getAllClubsLeaderboard($perPage, $page);

            return ResponseHelper::success([
                'scope'       => $scope,
                'leaderboard' => $leaderboardData['items'],
                'meta'        => [
                    'total'     => $leaderboardData['total'],
                    'per_page'  => $perPage,
                    'page'      => $page,
                    'last_page' => $leaderboardData['last_page'],
                ],
            ], 'Lấy bảng xếp hạng club thành công');
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
        $rankingMatches = (int) SystemSetting::where('key', 'ranking_matches')->first()?->value ?: 10;
        $excludedEmail = 'vrplus2018@gmail.com';

        // Precompute total_matches for ALL users with sport_id in a single CTE —
        // avoids running 5 correlated subqueries per row in both COUNT and SELECT.
        $totalMatchesCte = "
            SELECT us.user_id,
                (
                    SELECT COUNT(DISTINCT m.id) FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.home_team_id
                    WHERE tm.user_id = us.user_id AND t.sport_id = {$sportId} AND m.status = 'completed'
                ) + (
                    SELECT COUNT(DISTINCT m.id) FROM matches m
                    JOIN tournament_types tt ON m.tournament_type_id = tt.id
                    JOIN tournaments t ON tt.tournament_id = t.id
                    JOIN team_members tm ON tm.team_id = m.away_team_id
                    WHERE tm.user_id = us.user_id AND t.sport_id = {$sportId} AND m.status = 'completed'
                ) + (
                    SELECT COUNT(DISTINCT mm.id) FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                    WHERE mtm.user_id = us.user_id AND mnt.sport_id = {$sportId} AND mm.status = 'completed'
                ) + (
                    SELECT COUNT(DISTINCT mm.id) FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
                    WHERE mtm.user_id = us.user_id AND mnt.sport_id = {$sportId} AND mm.status = 'completed'
                ) + (
                    SELECT COUNT(DISTINCT mh.id) FROM match_histories mh
                    JOIN quick_matches qm ON mh.quick_match_id = qm.id
                    WHERE mh.user_id = us.user_id AND qm.status = 'completed'
                        AND (qm.competition_location_id IS NULL OR EXISTS (
                            SELECT 1 FROM competition_location_sport cls
                            WHERE cls.competition_location_id = qm.competition_location_id AND cls.sport_id = {$sportId}
                        ))
                ) AS total_matches
            FROM user_sport us
            WHERE us.sport_id = {$sportId}
        ";

        $scoreSubQuery = UserSportScore::query()
            ->select(
                'user_sport.user_id',
                DB::raw('MAX(user_sport_scores.score_value) as vndupr_score')
            )
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->groupBy('user_sport.user_id');

        // Join against CTE instead of correlated subqueries per row.
        $baseQuery = User::query()
            ->from(DB::raw("({$totalMatchesCte}) AS total_matches_cte"))
            ->joinSub($scoreSubQuery, 'scores', 'scores.user_id', '=', 'total_matches_cte.user_id')
            ->join('users', 'users.id', '=', 'total_matches_cte.user_id')
            ->where('users.email', '!=', $excludedEmail)
            ->select(
                'users.id',
                'scores.vndupr_score',
                'total_matches_cte.total_matches'
            )
            ->with(['clubs:id,name'])
            ->orderByDesc('scores.vndupr_score')
            ->having('total_matches', '>=', $rankingMatches);

        // Clone for COUNT to avoid re-running the CTE — same base query, just count.
        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $leaderboard = $baseQuery
            ->addSelect(
                'users.full_name',
                'users.visibility',
                'users.avatar_url',
                'users.is_anchor',
                'users.is_verified',
                'users.total_matches_has_anchor',
                DB::raw('ROW_NUMBER() OVER (ORDER BY scores.vndupr_score DESC) as rank')
            )
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = $leaderboard->map(function ($user) {
            return [
                'id'           => $user->id,
                'full_name'    => $user->full_name,
                'visibility'   => $user->visibility,
                'avatar_url'   => $user->avatar_url,
                'rank'         => (int) $user->rank,
                'vndupr_score' => round((float) $user->vndupr_score, 3),
                'clubs'        => $user->clubs->map(fn($c) => ['id' => $c->id, 'name' => $c->name]),
                'is_anchor'    => (bool) $user->is_anchor,
                'is_verify'    => (bool) (($user->total_matches_has_anchor ?? 0) >= 10),
            ];
        });

        return [
            'items'     => $items,
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
                'users.total_matches_has_anchor',
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
                'is_verify'  => (bool) (($user->total_matches_has_anchor ?? 0) >= 10),
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
        /** @var \App\Models\User $user */
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
                'users.total_matches_has_anchor',
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
                'is_verify'  => (bool) (($user->total_matches_has_anchor ?? 0) >= 10),
            ];
        });

        return [
            'items'    => $items,
            'total'    => $total,
            'last_page' => $lastPage,
        ];
    }

    private function getAllClubsLeaderboard(int $perPage, int $page): array
    {
        $query = Club::allClubs()
            ->with(['members.user.vnduprScores'])
            ->get()
            ->map(function ($club) {
                $club->max_score = (float) ($club->members
                    ->map(fn($m) => $m->user?->vnduprScores?->max('score_value') ?? 0)
                    ->max() ?? 0);
                return $club;
            })
            ->sortByDesc('max_score');

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $paginated = $query->slice($offset, $perPage)->values();

        $items = $paginated->map(function ($club, $index) use ($page, $perPage) {
            return [
                'id'               => $club->id,
                'name'             => $club->name,
                'logo_url'         => $club->logo_url,
                'is_verified'      => (bool) $club->is_verified,
                'quantity_members' => $club->members->count(),
                'max_score'        => $club->max_score,
                'rank'             => ($page - 1) * $perPage + $index + 1,
            ];
        });

        return [
            'items'     => $items,
            'total'     => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }
}
