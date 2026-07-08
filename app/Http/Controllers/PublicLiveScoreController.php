<?php

namespace App\Http\Controllers;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Services\MatchScoreService;
use Illuminate\Http\JsonResponse;

class PublicLiveScoreController extends Controller
{
    public function __construct(
        protected MatchScoreService $matchScoreService
    ) {}

    /**
     * Get live score for a match (public - no auth required).
     * Supports both tournament matches (Matches) and mini-matches (MiniMatch).
     */
    public function show(string $matchType, int $matchId): JsonResponse
    {
        if ($matchType === 'tournament') {
            return $this->tournamentMatch($matchId);
        }

        if ($matchType === 'mini') {
            return $this->miniMatch($matchId);
        }

        return response()->json([
            'success' => false,
            'message' => 'Loại trận đấu không hợp lệ',
        ], 400);
    }

    protected function tournamentMatch(int $matchId): JsonResponse
    {
        try {
            $data = $this->matchScoreService->getCurrentState($matchId);

            return response()->json([
                'success' => true,
                'data' => $data,
                'type' => 'tournament',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy trận đấu',
            ], 404);
        }
    }

    protected function miniMatch(int $matchId): JsonResponse
    {
        try {
            $match = MiniMatch::withFullRelations()->findOrFail($matchId);
            $miniTournament = $match->miniTournament;

            // Kèo chưa công bố (draft): chỉ public khi đã published
            if ($miniTournament->status === \App\Models\MiniTournament::STATUS_DRAFT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kèo đấu chưa được công bố',
                ], 404);
            }

            // Format response giống LiveScorePage.vue expect
            $team1Name = $match->team1?->name ?? 'Team 1';
            $team2Name = $match->team2?->name ?? 'Team 2';
            $team1Avatar = $match->team1?->avatar ?? null;
            $team2Avatar = $match->team2?->avatar ?? null;

            // Parse results
            $sets = [];
            if ($match->relationLoaded('results')) {
                $grouped = $match->results->groupBy('set_number');
                $t1Id = $match->team1?->id;
                $t2Id = $match->team2?->id;

                foreach ($grouped as $setNumber => $setResults) {
                    $entry1 = $setResults->firstWhere('team_id', $t1Id);
                    $entry2 = $setResults->firstWhere('team_id', $t2Id);
                    $s1 = (int) ($entry1?->score ?? 0);
                    $s2 = (int) ($entry2?->score ?? 0);

                    $winner = null;
                    if ($s1 > $s2) {
                        $winner = 'team1';
                    } elseif ($s2 > $s1) {
                        $winner = 'team2';
                    }

                    $sets[] = [
                        'set_number' => (int) $setNumber,
                        'team1_score' => $s1,
                        'team2_score' => $s2,
                        'winner' => $winner,
                    ];
                }
            }

            $formatMiniTeamMembers = function ($team) {
                if (!$team) return [];
                return $team->members->map(function ($member) {
                    $user = $member->user;
                    if (!$user) return null;
                    $vnduprScore = $user->sports
                        ? $user->sports->flatMap(fn ($s) => $s->scores ?? [])
                              ->first(fn ($score) => $score->score_type === 'vndupr_score')
                              ?->score_value
                        : null;
                    $avatar = $user->avatar_url
                        ? (str_starts_with($user->avatar_url, 'http')
                            ? $user->avatar_url
                            : config('app.frontend_url') . '/storage/' . $user->avatar_url)
                        : null;
                    return [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'avatar' => $avatar,
                        'vndupr' => $vnduprScore,
                    ];
                })->filter()->values()->toArray();
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $match->id,
                    'name' => $match->name ?? "{$team1Name} vs {$team2Name}",
                    'live_status' => $match->status === 'going_on' ? 'playing' : 'waiting',
                    'started_at' => null,
                    'scheduled_at' => null,
                    'current_set' => 1,
                    'serving_team_id' => null,
                    'serving_position' => 0,
                    'team1_timeout_used' => 0,
                    'team2_timeout_used' => 0,
                    'version' => 0,
                    'elapsed_seconds' => null,
                    'referee_name' => null,
                    'side_switch_interval' => null,
                    'team1' => [
                        'id' => $match->team1?->id,
                        'name' => $team1Name,
                        'avatar' => $team1Avatar,
                        'members' => $formatMiniTeamMembers($match->team1),
                    ],
                    'team2' => [
                        'id' => $match->team2?->id,
                        'name' => $team2Name,
                        'avatar' => $team2Avatar,
                        'members' => $formatMiniTeamMembers($match->team2),
                    ],
                    'sets' => $sets,
                    'status' => $match->status,
                    'tournament' => $miniTournament ? [
                        'id' => $miniTournament->id,
                        'name' => $miniTournament->name ?? null,
                        'poster_url' => null,
                        'start_date' => null,
                        'end_date' => null,
                        'location_name' => $miniTournament->location_name ?? null,
                        'location_address' => $miniTournament->location_address ?? null,
                    ] : null,
                    'rules' => null,
                    'match_rules' => null,
                ],
                'type' => 'mini',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy trận đấu',
            ], 404);
        }
    }
}
