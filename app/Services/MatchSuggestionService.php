<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\ParticipantTierDTO;
use App\DTO\PlayerContextDTO;
use App\Enums\PlayerTier;
use App\Models\MiniParticipant;
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
     */
    public function generate(MatchSuggestionRequestDTO $request): MatchSuggestionResponseDTO
    {
        $miniTournamentId = $request->mini_tournament_id;

        // Build player contexts: merge FE tier with DB stats
        $players = $this->buildPlayerContexts($miniTournamentId, $request->participants);
        
        // Apply backup filter
        $players = $this->filterByBackup($players, $request->settings->organizer_as_backup);

        // Load user data with sports for response
        $userDataMap = $this->loadUserDataMap($players);

        return $this->schedulerService->generate($players, $request, $userDataMap);
    }

    /**
     * Regenerate suggestion - excludes players from previous suggestion.
     */
    public function regenerate(
        MatchSuggestionRequestDTO $request,
        ?MatchSuggestionResponseDTO $previousSuggestion
    ): MatchSuggestionResponseDTO {
        $excludeIds = [];

        if ($previousSuggestion && $previousSuggestion->match) {
            $team1UserIds = array_column($previousSuggestion->match->team1->members, 'user_id');
            $team2UserIds = array_column($previousSuggestion->match->team2->members, 'user_id');
            $excludeIds = array_merge(
                array_filter($team1UserIds),
                array_filter($team2UserIds)
            );
        }

        // Create new request with exclusion and new seed
        $newRequest = new MatchSuggestionRequestDTO(
            mini_tournament_id: $request->mini_tournament_id,
            participants: $request->participants,
            settings: $request->settings,
            seed: $request->seed ?? random_int(1, 999999),
            exclude_player_ids: $excludeIds,
        );

        return $this->generate($newRequest);
    }

    /**
     * Build player contexts: merge FE data (tier) with DB data (stats, gender, vndupr).
     * 
     * IMPORTANT: Gender is read from DB, not from FE.
     * Guests are included in the pool - they should be treated as normal participants.
     */
    private function buildPlayerContexts(int $miniTournamentId, array $feParticipants): array
    {
        // FE sends mini_participant_id + tier
        // Create lookup map
        $feTierMap = [];
        foreach ($feParticipants as $feP) {
            $feTierMap[$feP->mini_participant_id] = $feP->tier;
        }

        // Query DB for participant details (exclude absent users)
        $participantIds = array_column($feParticipants, 'mini_participant_id');
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('id', $participantIds)
            ->where('is_absent', false)
            ->with(['user:id,full_name,avatar_url,gender', 'guarantor'])
            ->get()
            ->keyBy('id');

        // Query stats from DB
        $playedCounts = $this->matchHistoryRepository->getPlayedCounts($miniTournamentId);
        $consecutiveCounts = $this->matchHistoryRepository->getConsecutiveCounts($miniTournamentId);
        $waitingRounds = $this->matchHistoryRepository->getWaitingRounds($miniTournamentId);
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
                vndupr_score: $vnduprScore,
                partner_ids: $partnerIds,
                is_checked_in: $participant->checked_in_at !== null,
                is_playing: $userId ? in_array($userId, $playingParticipants) : false,
                skip_next_round: $participant->skip_next_round ?? false,
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
