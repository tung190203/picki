<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\ParticipantTierDTO;
use App\DTO\PlayerContextDTO;
use App\Enums\PlayerTier;
use App\Models\MiniParticipant;
use App\Models\MiniTournamentSession;
use App\Models\User;
use App\Models\UserSportScore;
use App\Repositories\MatchHistoryRepository;

class MatchSuggestionService
{
    public function __construct(
        private MatchHistoryRepository $matchHistoryRepository,
        private SchedulerService $schedulerService,
    ) {}

    /**
     * Generate match suggestion.
     * - Participants list & tier from Frontend (source of truth)
     * - Stats from Database
     * - Gender from Database (not FE)
     *
     * Also seeds a MiniTournamentSession row with the first picked combo so
     * that the very first /regenerate call has a baseline to compare against.
     */
    public function generate(MatchSuggestionRequestDTO $request): MatchSuggestionResponseDTO
    {
        $miniTournamentId = $request->mini_tournament_id;

        // Get mini tournament to check if payment is required
        $miniTournament = \App\Models\MiniTournament::find($miniTournamentId);
        $needsPaymentCheck = $miniTournament
            && $miniTournament->has_fee
            && !$miniTournament->auto_split_fee
            && !$miniTournament->use_club_fund;

        // Build player contexts: merge FE tier with DB stats
        $players = $this->buildPlayerContexts($miniTournamentId, $request->participants, $needsPaymentCheck);

        // Apply backup filter
        $players = $this->filterByBackup($players, $request->settings->organizer_as_backup);

        // Load user data with sports for response
        $userDataMap = $this->loadUserDataMap($players);

        // Reset session history so the first generate call always starts fresh.
        // The picked combo is recorded afterwards, so regenerate() can rotate.
        $session = $this->loadOrCreateSession($miniTournamentId);
        $session->clearHistory();

        $response = $this->schedulerService->generate($players, $request, $userDataMap, $needsPaymentCheck);

        $this->rememberPickedCombo($response, $session);

        return $response;
    }

    /**
     * Regenerate suggestion - excludes players from previous suggestion and
     * rotates to the next unseen combo.
     *
     * State is persisted in `mini_tournament_sessions`. The caller passes the
     * previous suggestion (so we know where to start from in the rotation
     * cycle); on subsequent calls the state alone is enough.
     */
    public function regenerate(
        MatchSuggestionRequestDTO $request,
        ?MatchSuggestionResponseDTO $previousSuggestion
    ): MatchSuggestionResponseDTO {
        $miniTournamentId = $request->mini_tournament_id;

        // Get mini tournament to check if payment is required
        $miniTournament = \App\Models\MiniTournament::find($miniTournamentId);
        $needsPaymentCheck = $miniTournament
            && $miniTournament->has_fee
            && !$miniTournament->auto_split_fee
            && !$miniTournament->use_club_fund;

        // Build player contexts (full pool, no exclusion - rotation handles dedup)
        $players = $this->buildPlayerContexts($miniTournamentId, $request->participants, $needsPaymentCheck);

        // Apply backup filter
        $players = $this->filterByBackup($players, $request->settings->organizer_as_backup);

        // Load user data with sports for response
        $userDataMap = $this->loadUserDataMap($players);

        $session = $this->loadOrCreateSession($miniTournamentId);

        // Forward-compat: prepend the previous suggestion's signature to history
        // so the very first regenerate() respects the rule "current != previous".
        if ($previousSuggestion && $previousSuggestion->match) {
            $previousSignature = $this->extractSignatureFromResponse($previousSuggestion);
            if ($previousSignature !== null) {
                $session->remember($previousSignature);
            }
        }

        // Enumerate all valid candidates (no exclusion in the scheduler itself).
        $pool = $this->buildPoolForRotation($players, $request, $needsPaymentCheck);

        if (count($pool) < 4) {
            $messages = ['Pool has less than 4 players after filters for rotation.'];
            return $this->schedulerService->generate($players, $request, $userDataMap, $needsPaymentCheck);
        }

        $evaluation = $this->schedulerService->enumerateCandidates($pool, $request, $userDataMap);
        $candidates = $evaluation['candidates'];
        $totalCandidates = (int) $evaluation['total_candidates'];

        if (empty($candidates)) {
            return $this->schedulerService->generate($players, $request, $userDataMap, $needsPaymentCheck);
        }

        // Pick the first candidate whose signature is not in the history.
        $picked = null;
        $pickedIndex = -1;
        foreach ($candidates as $idx => $candidate) {
            if (!$session->hasTried($candidate['signature'])) {
                $picked = $candidate;
                $pickedIndex = $idx;
                break;
            }
        }

        $wrapped = false;

        // If everything's been tried, wrap around: clear history and pick first.
        if ($picked === null) {
            $wrapped = true;
            $session->clearHistory();
            // Re-add only the previous suggestion so we don't immediately
            // repeat it on the wrap-around.
            if ($previousSuggestion && $previousSuggestion->match) {
                $previousSignature = $this->extractSignatureFromResponse($previousSuggestion);
                if ($previousSignature !== null) {
                    $session->remember($previousSignature);
                }
            }
            // Find first candidate again
            foreach ($candidates as $idx => $candidate) {
                if (!$session->hasTried($candidate['signature'])) {
                    $picked = $candidate;
                    $pickedIndex = $idx;
                    break;
                }
            }
        }

        if ($picked === null) {
            // Shouldn't happen unless history contains every signature.
            $picked = $candidates[0];
            $pickedIndex = 0;
        }

        $response = $this->buildRotationResponse($players, $request, $userDataMap, $picked, $pickedIndex, $totalCandidates, $wrapped);

        // Persist new picked combo; bump rotation_index.
        $this->rememberPickedCombo($response, $session);
        $session->rotation_index = ($session->rotation_index ?? 0) + 1;
        $session->last_generated_at = now();
        $session->save();

        return $response;
    }

    /**
     * Helper: build the (real) pool used by the scheduler, mirroring the
     * scheduler's filters but without `exclude_player_ids`. Rotation handles
     * dedup by skipping signatures that were previously returned.
     */
    private function buildPoolForRotation(array $players, MatchSuggestionRequestDTO $request, bool $needsPaymentCheck): array
    {
        // The scheduler exposes buildPool() only privately; use the wrapper
        // generate() to enforce filters via a temporary request.
        // Instead, replicate the filter logic here (kept in sync with SchedulerService::buildPool).
        $pool = [];
        foreach ($players as $player) {
            if ($player->is_absent) continue;
            if ($needsPaymentCheck && $player->payment_status !== null && $player->payment_status !== \App\Enums\PaymentStatusEnum::CONFIRMED->value) {
                continue;
            }
            if ($player->is_playing) continue;
            if ($player->skip_next_round) continue;
            if ($request->settings->prevent_three_consecutive && $player->consecutive_count >= 2) continue;
            if (!$request->settings->organizer_as_backup && $player->is_backup) continue;

            $pool[] = $player;
        }

        // Skip exclude_player_ids for rotation - the rotation manager removes
        // candidates by signature, not by hard exclusion.
        // Note: this honours the FE-supplied exclude_player_ids coming from
        // older clients (which is empty for the rotation code path anyway).
        if ($request->exclude_player_ids) {
            $pool = array_filter($pool, fn($p) => !in_array($p->user_id, $request->exclude_player_ids));
            $pool = array_values($pool);
        }

        $pool = $this->stableShuffle($pool, $request->seed);
        return $pool;
    }

    /**
     * Stable shuffle using a deterministic seed (same as SchedulerService).
     */
    private function stableShuffle(array $pool, ?int $seed): array
    {
        if ($seed === null) {
            return $pool;
        }
        mt_srand($seed);
        $shuffled = $pool;
        for ($i = count($shuffled) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$shuffled[$i], $shuffled[$j]] = [$shuffled[$j], $shuffled[$i]];
        }
        mt_srand();
        return $shuffled;
    }

    /**
     * Extract the sorted user_id signature from a previous suggestion response.
     */
    private function extractSignatureFromResponse(MatchSuggestionResponseDTO $response): ?array
    {
        if (!$response->match) {
            return null;
        }
        $team1Ids = array_column($response->match->team1->members, 'user_id');
        $team2Ids = array_column($response->match->team2->members, 'user_id');
        $ids = array_merge($team1Ids, $team2Ids);
        $ids = array_values(array_filter($ids, fn($v) => $v !== null));
        if (count($ids) !== 4) {
            return null;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Build a MatchSuggestionResponseDTO from a single candidate (used by rotation).
     */
    private function buildRotationResponse(
        array $players,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
        array $candidate,
        int $selectedOffset,
        int $totalCandidates,
        bool $wrapped,
    ): MatchSuggestionResponseDTO {
        $seed = $request->seed ?? random_int(1, 999999);

        // Compute the full pool (same as scheduler would) so waiting/backup
        // statistics reflect the full rotation pool.
        $pool = $this->buildPoolForRotation($players, $request, false);

        $match = $this->schedulerService->buildMatchDTOForCandidate(
            $candidate['team_a'],
            $candidate['team_b'],
            $userDataMap,
            $candidate['is_high_tier'],
        );

        $selectedIds = array_column($pool, 'user_id');
        $waiting = $this->schedulerService->buildWaitingListPublic($pool, $selectedIds);
        $backup = $this->schedulerService->getBackupIfNeededPublic($selectedIds, $request->settings->organizer_as_backup);
        $statistics = $this->schedulerService->calculateStatisticsPublic($match, $pool, $selectedIds);

        return new MatchSuggestionResponseDTO(
            match: $match,
            waiting_players: $waiting,
            backup_used: $backup !== null,
            backup_player: $backup,
            statistics: $statistics,
            seed: $seed,
            rules_applied: array_values(array_unique($candidate['rules_applied'] ?? [])),
            messages: $wrapped ? ['Rotation wrapped - showing first combo again.'] : [],
            total_candidates: $totalCandidates,
            selected_offset: $selectedOffset,
            wrapped: $wrapped,
        );
    }

    /**
     * Store the picked combo signature on the session row (idempotent).
     */
    private function rememberPickedCombo(MatchSuggestionResponseDTO $response, MiniTournamentSession $session): void
    {
        $sig = $this->extractSignatureFromResponse($response);
        if ($sig === null) {
            return;
        }
        $session->remember($sig);
        $session->last_generated_at = now();
        $session->save();
    }

    /**
     * Load or create the per-tournament session row.
     */
    private function loadOrCreateSession(int $miniTournamentId): MiniTournamentSession
    {
        return MiniTournamentSession::firstOrCreate(
            ['mini_tournament_id' => $miniTournamentId],
            [
                'tried_suggestions' => [],
                'rotation_index' => 0,
            ],
        );
    }

    /**
     * Build player contexts: merge FE data (tier) with DB data (stats, gender, vndupr).
     * 
     * IMPORTANT: Gender is read from DB, not from FE.
     * Guests are included in the pool - they should be treated as normal participants.
     */
    private function buildPlayerContexts(int $miniTournamentId, array $feParticipants, bool $needsPaymentCheck): array
    {
        // FE sends mini_participant_id + tier
        // Create lookup map
        $feTierMap = [];
        foreach ($feParticipants as $feP) {
            $feTierMap[$feP->mini_participant_id] = $feP->tier;
        }

        // Query DB for participant details
        $participantIds = array_column($feParticipants, 'mini_participant_id');
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('id', $participantIds)
            ->with(['user:id,full_name,avatar_url,gender', 'guarantor'])
            ->get()
            ->keyBy('id');

        // Query stats from DB
        $playedCounts = $this->matchHistoryRepository->getPlayedCounts($miniTournamentId);
        $consecutiveCounts = $this->matchHistoryRepository->getConsecutiveCounts($miniTournamentId);
        $waitingRounds = $this->matchHistoryRepository->getWaitingRounds($miniTournamentId);
        $lastPlayedRounds = $this->matchHistoryRepository->getLastPlayedRounds($miniTournamentId);
        $partnerHistory = $this->matchHistoryRepository->getPartnerHistory($miniTournamentId);
        $playingParticipants = $this->matchHistoryRepository->getPlayingParticipants($miniTournamentId);

        // Get VN DUPR scores
        $vnduprScores = $this->getVnduprScores($miniTournamentId);

        $players = [];

        foreach ($feParticipants as $feP) {
            $participant = $participants->get($feP->mini_participant_id);
            if (!$participant) continue;

            $user = $participant->user;
            $userId = $user?->id;

            // Guest: get name from participant data if user is null
            $fullName = $user?->full_name
                ?? $participant->guest_name
                ?? 'Guest';
            
            $avatarUrl = $user?->avatar_url
                ?? $participant->guest_avatar;

            // Gender from DB (not FE) - critical for gender balancing
            $gender = $user?->gender ?? null;

            // VN DUPR score
            $vnduprScore = $userId ? ($vnduprScores[$userId] ?? null) : null;

            // Partner IDs
            $partnerIds = $userId ? ($partnerHistory[$userId] ?? []) : [];

            // Use tier from Frontend (source of truth), don't recalculate
            $tier = $feP->tier;

            $players[] = new PlayerContextDTO(
                mini_participant_id: $participant->id,
                user_id: $userId,
                full_name: $fullName,
                avatar_url: $avatarUrl,
                tier: $tier,
                is_manual_override: true,
                gender: $gender,
                is_guest: $participant->is_guest,
                played_count: $userId ? ($playedCounts[$userId] ?? 0) : 0,
                consecutive_count: $userId ? ($consecutiveCounts[$userId] ?? 0) : 0,
                waiting_rounds: $userId ? ($waitingRounds[$userId] ?? 0) : 0,
                last_played_round: $userId ? ($lastPlayedRounds[$userId] ?? null) : null,
                vndupr_score: $vnduprScore,
                partner_ids: $partnerIds,
                is_checked_in: $participant->checked_in_at !== null,
                is_playing: $userId ? in_array($userId, $playingParticipants) : false,
                skip_next_round: $participant->skip_next_round ?? false,
                is_absent: $participant->is_absent,
                payment_status: $needsPaymentCheck ? ($participant->payment_status?->value ?? null) : null,
                is_backup: false,
            );
        }

        return $players;
    }

    /**
     * Get VN DUPR scores for all participants.
     * Returns array: user_id => score_value
     */
    private function getVnduprScores(int $miniTournamentId): array
    {
        // Get all user IDs from participants
        $participantUserIds = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereNotNull('user_id')
            ->where('is_guest', false)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($participantUserIds)) {
            return [];
        }

        // Get user_sport IDs for these users (sport_id = 1 for Pickleball)
        $userSportIds = \Illuminate\Support\Facades\DB::table('user_sport')
            ->whereIn('user_id', $participantUserIds)
            ->where('sport_id', 1)
            ->pluck('id')
            ->values()
            ->toArray();

        if (empty($userSportIds)) {
            return [];
        }

        // Get latest VN DUPR scores
        $scores = UserSportScore::whereIn('user_sport_id', $userSportIds)
            ->where('score_type', 'vndupr_score')
            ->get()
            ->groupBy(fn($s) => $s->userSport?->user_id)
            ->map(fn($col) => $col->sortByDesc('created_at')->first()?->score_value)
            ->filter()
            ->toArray();

        return $scores;
    }

    /**
     * Load user data with sports for response formatting.
     * 
     * @param PlayerContextDTO[] $players
     * @return array [user_id => ['visibility' => string, 'sports' => array]]
     */
    private function loadUserDataMap(array $players): array
    {
        $userIds = array_filter(array_column($players, 'user_id'));
        
        if (empty($userIds)) {
            return [];
        }

        $users = User::whereIn('id', $userIds)
            ->with('sports.scores')
            ->get()
            ->keyBy('id');

        $userDataMap = [];
        foreach ($players as $player) {
            if (!$player->user_id) continue;
            
            $user = $users->get($player->user_id);
            if (!$user) continue;

            $sports = [];
            foreach ($user->sports as $userSport) {
                $scores = [];
                foreach ($userSport->scores as $score) {
                    $scores[$score->score_type] = (string) $score->score_value;
                }
                
                $sports[] = [
                    'sport_id' => $userSport->sport_id,
                    'scores' => [
                        'personal_score' => $scores['personal_score'] ?? '0.000',
                        'dupr_score' => $scores['dupr_score'] ?? '0.000',
                        'vndupr_score' => $scores['vndupr_score'] ?? '0.000',
                        'trinh_score' => $scores['trinh_score'] ?? '0.000',
                    ],
                ];
            }

            $userDataMap[$player->user_id] = [
                'visibility' => $user->visibility ?? 'open',
                'sports' => $sports,
            ];
        }

        return $userDataMap;
    }

    private function filterByBackup(array $players, bool $organizerAsBackup): array
    {
        if (!$organizerAsBackup) {
            return array_values(array_filter($players, fn($p) => !$p->is_backup));
        }

        return $players;
    }
}
