<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\ParticipantTierDTO;
use App\DTO\PlayerContextDTO;
use App\Enums\MatchTier;
use App\Models\MiniParticipant;
use App\Models\User;
use App\Repositories\MatchHistoryRepository;

class MatchSuggestionService
{
    public function __construct(
        private MatchHistoryRepository $matchHistoryRepository,
        private SchedulerService $schedulerService,
    ) {}

    /**
     * Generate match suggestion.
     * - Participants list & tier từ Frontend (source of truth)
     * - Stats từ Database
     */
    public function generate(MatchSuggestionRequestDTO $request): MatchSuggestionResponseDTO
    {
        $miniTournamentId = $request->mini_tournament_id;

        // Build player contexts: merge FE tier với DB stats
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
     * Build player contexts: merge FE data (tier) với DB data (stats).
     * 
     * @param int $miniTournamentId
     * @param ParticipantTierDTO[] $feParticipants - Tier từ Frontend
     * @return PlayerContextDTO[]
     */
    private function buildPlayerContexts(int $miniTournamentId, array $feParticipants): array
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
            ->with(['user', 'team.members'])
            ->get()
            ->keyBy('id');

        // Query stats from DB
        $playedCounts = $this->matchHistoryRepository->getPlayedCounts($miniTournamentId);
        $consecutiveCounts = $this->matchHistoryRepository->getConsecutiveCounts($miniTournamentId);
        $partnerHistory = $this->matchHistoryRepository->getPartnerHistory($miniTournamentId);
        $playingParticipants = $this->matchHistoryRepository->getPlayingParticipants($miniTournamentId);

        $players = [];

        foreach ($feParticipants as $feP) {
            $participant = $participants->get($feP->mini_participant_id);
            if (!$participant) continue;

            $user = $participant->user;
            if (!$user) continue;

            $userId = $user->id;
            $partnerIds = $partnerHistory[$userId] ?? [];

            // Use tier từ Frontend (source of truth), không tự tính lại
            $tier = $feP->tier;

            $players[] = new PlayerContextDTO(
                mini_participant_id: $participant->id,
                user_id: $userId,
                full_name: $user->full_name,
                avatar_url: $user->avatar_url,
                tier: $tier,  // Từ FE, không phải từ DB
                is_manual_override: true,  // FE đã set tier thủ công
                played_count: $playedCounts[$userId] ?? 0,
                consecutive_count: $consecutiveCounts[$userId] ?? 0,
                rest_count: 0,
                partner_ids: $partnerIds,
                is_checked_in: true,  // FE chỉ gửi người đã check-in
                is_playing: in_array($userId, $playingParticipants),
                skip_next_round: false,  // FE không gửi người bị skip
                is_backup: false,
            );
        }

        return $players;
    }

    /**
     * Load user data with sports for response formatting.
     * 
     * @param PlayerContextDTO[] $players
     * @return array [user_id => ['visibility' => string, 'sports' => array]]
     */
    private function loadUserDataMap(array $players): array
    {
        $userIds = array_column($players, 'user_id');
        
        $users = User::whereIn('id', $userIds)
            ->with('sports.scores')
            ->get()
            ->keyBy('id');

        $userDataMap = [];
        foreach ($players as $player) {
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
