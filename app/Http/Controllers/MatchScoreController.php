<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Matches;
use App\Services\MatchScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchScoreController extends Controller
{
    private const ERR_NOT_FOUND = 'Match not found';
    private const ERR_FORBIDDEN = 'Không có quyền';

    public function __construct(
        protected MatchScoreService $matchScoreService
    ) {}

    public function start(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        $match = Matches::find($matchId);

        if (!$match) {
            return ResponseHelper::error(self::ERR_NOT_FOUND, 404);
        }

        if (!$match->hasScoringPermission($user->id)) {
            return ResponseHelper::error(self::ERR_FORBIDDEN, 403);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'serving_team_id' => 'required|integer',
        ]);

        // Prefer the user_id sent in body (most recent referee action); fall back to the authenticated user.
        $refereeUserId = $validated['user_id'] ?? $user->id;

        $data = $this->matchScoreService->startMatch(
            $matchId,
            $validated['serving_team_id'],
            $refereeUserId
        );

        return ResponseHelper::single($data, 'Match started successfully');
    }

    public function update(Request $request, int $matchId): JsonResponse
    {
        $user = $request->user();
        $match = Matches::find($matchId);

        if (!$match) {
            return ResponseHelper::error(self::ERR_NOT_FOUND, 404);
        }

        if (!$match->hasScoringPermission($user->id)) {
            return ResponseHelper::error(self::ERR_FORBIDDEN, 403);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'team1_score' => 'required|integer|min:0',
            'team2_score' => 'required|integer|min:0',
            'serving_team_id' => 'required|integer',
            'serving_position' => 'nullable|integer|min:0|max:1',
            'set_number' => 'nullable|integer|min:1',
            'team1_timeout_used' => 'nullable|integer|min:0',
            'team2_timeout_used' => 'nullable|integer|min:0',
            'live_status' => 'nullable|string',
        ]);

        $validated['set_number'] = $validated['set_number'] ?? 1;

        // Prefer the user_id sent in body (most recent referee action); fall back to the authenticated user.
        $refereeUserId = $validated['user_id'] ?? $user->id;

        $data = $this->matchScoreService->updateState(
            $matchId,
            $validated,
            $refereeUserId
        );

        return ResponseHelper::single($data, 'Score updated successfully');
    }

    public function current(int $matchId): JsonResponse
    {
        try {
            $data = $this->matchScoreService->getCurrentState($matchId);
            return ResponseHelper::single($data, 'Current score retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error(self::ERR_NOT_FOUND, 404);
        }
    }
}
