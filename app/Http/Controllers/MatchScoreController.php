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
            'version' => 'required|integer',
        ]);

        // Prefer the user_id sent in body (most recent referee action); fall back to the authenticated user.
        $refereeUserId = $validated['user_id'] ?? $user->id;

        try {
            $data = $this->matchScoreService->startMatch(
                $matchId,
                $validated['serving_team_id'],
                $validated['version'],
                $refereeUserId
            );

            return ResponseHelper::single($data, 'Match started successfully');
        } catch (\App\Exceptions\VersionConflictException $e) {
            return $this->handleVersionConflict($e, $matchId);
        }
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
            'version' => 'required|integer',
        ]);

        $validated['set_number'] = $validated['set_number'] ?? 1;

        // Prefer the user_id sent in body (most recent referee action); fall back to the authenticated user.
        $refereeUserId = $validated['user_id'] ?? $user->id;

        try {
            $data = $this->matchScoreService->updateState(
                $matchId,
                $validated,
                $refereeUserId
            );

            return ResponseHelper::single($data, 'Score updated successfully');
        } catch (\App\Exceptions\VersionConflictException $e) {
            return $this->handleVersionConflict($e, $matchId);
        }
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

    protected function handleVersionConflict(\App\Exceptions\VersionConflictException $e, int $matchId): JsonResponse
    {
        $currentMatch = $e->getMatch();

        if ($currentMatch) {
            $currentState = $this->matchScoreService->getCurrentState($matchId);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VERSION_CONFLICT',
                    'message' => 'Trạng thái đã được cập nhật bởi yêu cầu khác. Vui lòng thử lại.',
                ],
                'data' => $currentState,
            ], 409);
        }

        return ResponseHelper::error('Version conflict', 409);
    }
}
