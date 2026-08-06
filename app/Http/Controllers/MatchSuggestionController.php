<?php

namespace App\Http\Controllers;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\Http\Requests\MatchSuggestionRequest;
use App\Http\Resources\MatchSuggestionResource;
use App\Models\MiniTournament;
use App\Services\MatchSuggestionService;
use Illuminate\Http\JsonResponse;

/**
 * @group Match Suggestions
 * 
 * APIs for generating match suggestions in Mini Tournaments.
 * 
 * **Business Rules:**
 * - Frontend (mobile app) is the source of truth for participant list and tier (purple/red/yellow/green).
 * - Only participants that are available for matching should be sent.
 * - Backend uses tier from Frontend and merges with DB stats (played_count, consecutive, gender, vndupr, etc.)
 * - Gender is read from Database, NOT from Frontend.
 * - Guests are included in the pool and treated as normal participants.
 */
class MatchSuggestionController extends Controller
{
    public function __construct(
        protected MatchSuggestionService $matchSuggestionService,
    ) {}

    /**
     * Generate match suggestion.
     * 
     * Generate a new match suggestion based on:
     * - Participants list from Frontend (source of truth for availability and tier)
     * - Settings for fair play, balance, etc.
     * - Stats (played_count, consecutive) from Database
     * 
     * @unauthenticated
     */
    public function generate(MatchSuggestionRequest $request, int $miniTournamentId): MatchSuggestionResource|JsonResponse
    {
        $bodyTournamentId = $request->input('mini_tournament_id');
        if ($bodyTournamentId && $bodyTournamentId !== $miniTournamentId) {
            return response()->json([
                'success' => false,
                'message' => 'mini_tournament_id không khớp.',
            ], 422);
        }

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->is_session_started) {
            return response()->json([
                'success' => false,
                'message' => 'Session chưa bắt đầu.',
            ], 422);
        }

        $dto = MatchSuggestionRequestDTO::fromArray([
            'mini_tournament_id' => $miniTournamentId,
            ...$request->validated(),
        ]);

        try {
            $suggestion = $this->matchSuggestionService->generate($dto);
            return new MatchSuggestionResource($suggestion);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Regenerate match suggestion.
     * 
     * Generate a new suggestion excluding players from the previous suggestion.
     * Automatically excludes players from previous match and generates with new seed.
     * 
     * @unauthenticated
     */
    public function regenerate(MatchSuggestionRequest $request, int $miniTournamentId): MatchSuggestionResource|JsonResponse
    {
        $bodyTournamentId = $request->input('mini_tournament_id');
        if ($bodyTournamentId && $bodyTournamentId !== $miniTournamentId) {
            return response()->json([
                'success' => false,
                'message' => 'mini_tournament_id không khớp.',
            ], 422);
        }

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->is_session_started) {
            return response()->json([
                'success' => false,
                'message' => 'Session chưa bắt đầu.',
            ], 422);
        }

        $dto = MatchSuggestionRequestDTO::fromArray([
            'mini_tournament_id' => $miniTournamentId,
            ...$request->validated(),
        ]);
        
        $previousSuggestion = null;
        if ($request->has('previous_suggestion') && is_array($request->input('previous_suggestion'))) {
            $previousSuggestion = MatchSuggestionResponseDTO::fromArray($request->input('previous_suggestion'));
        }

        try {
            $suggestion = $this->matchSuggestionService->regenerate($dto, $previousSuggestion);
            return new MatchSuggestionResource($suggestion);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
