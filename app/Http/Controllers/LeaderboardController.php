<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\TeamLeaderboardResource;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Models\TournamentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $leaderboard = $rankings
            ->groupBy('team_id')
            ->map(function ($teamRankings, $teamId) use ($teamStats, $sportId) {
                $firstRanking = $teamRankings->first();
                $team = $firstRanking->team;
                $stats = $teamStats[$teamId] ?? ['total_matches' => 0, 'win_rate' => 0, 'vndupr_avg' => 0];

                $rank = $this->resolveFinalRank($teamRankings);

                $tournamentTypes = $teamRankings->map(fn($r) => [
                    'id'   => $r->tournamentType->id,
                    'name' => $r->tournamentType->format_label ?? 'N/A',
                ])->values()->all();

                $members = $team->members->map(fn($m) => [
                    'id'         => $m->id,
                    'full_name'  => $m->full_name,
                    'avatar_url' => $m->avatar_url,
                ])->values()->all();

                return new TeamLeaderboardResource([
                    'id'            => $team->id,
                    'name'          => $team->name,
                    'avatar'        => $team->avatar,
                    'vndupr_avg'    => $stats['vndupr_avg'],
                    'members'       => $members,
                    'tournament_types' => $tournamentTypes,
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
}
