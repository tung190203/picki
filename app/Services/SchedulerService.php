<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\PlayerContextDTO;
use App\DTO\SuggestionMatchDTO;
use App\DTO\TeamMatchDTO;
use App\DTO\TeamMatchMemberDTO;
use App\Enums\MatchTier;

class SchedulerService
{
    /**
     * @param array $players PlayerContextDTO[]
     * @param MatchSuggestionRequestDTO $request
     * @param array $userDataMap [user_id => ['visibility' => string, 'sports' => array]]
     */
    public function generate(
        array $players,
        MatchSuggestionRequestDTO $request,
        array $userDataMap = []
    ): MatchSuggestionResponseDTO {
        $seed = $request->seed ?? random_int(1, 999999);
        $rulesApplied = [];
        $messages = [];
        $settings = $request->settings;

        $pool = $this->buildPool($players, $request);
        
        if (count($pool) < 4) {
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        $selected = $this->selectPlayers($pool, $request);
        
        if (count($selected) < 4) {
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        $match = null;
        // Ưu tiên ghép "Căng tay" (A với A, B với B) trước
        $sameTierMatch = $this->tryCreateSameTierMatch($pool);
        if ($sameTierMatch) {
            $match = $sameTierMatch;
            $rulesApplied[] = 'same_tier_match';
        } elseif ($settings->prefer_high_tier_match && $this->canFormHighTierMatch($pool)) {
            $match = $this->createHighTierMatch($pool, $userDataMap);
            $rulesApplied[] = 'prefer_high_tier_match';
        } else {
            $match = $this->balanceTeams($selected, $userDataMap);
        }

        $selectedIds = array_column($selected, 'user_id');
        $waiting = $this->buildWaitingList($pool, $selectedIds);
        $backup = $this->getBackupIfNeeded($selected, $settings->organizer_as_backup);
        $statistics = $this->calculateStatistics($match, $selected, $waiting, $backup);

        return new MatchSuggestionResponseDTO(
            match: $match,
            waiting_players: $waiting,
            backup_used: $backup !== null,
            backup_player: $backup,
            statistics: $statistics,
            seed: $seed,
            rules_applied: $rulesApplied,
            messages: $messages,
        );
    }

    private function buildPool(array $players, MatchSuggestionRequestDTO $request): array
    {
        $pool = [];

        foreach ($players as $player) {
            if (!$this->filterByCheckIn($player)) continue;
            if (!$this->filterByPlayingStatus($player)) continue;
            if (!$this->filterBySkipStatus($player)) continue;
            if (!$this->filterByForcedRest($player, $request->settings->prevent_three_consecutive)) continue;

            $pool[] = $player;
        }

        if ($request->exclude_player_ids) {
            $pool = array_filter($pool, fn($p) => !in_array($p->user_id, $request->exclude_player_ids));
            $pool = array_values($pool);
        }

        $pool = $this->shuffleWithSeed($pool, $request->seed);

        return $pool;
    }

    private function filterByCheckIn(PlayerContextDTO $player): bool
    {
        return $player->is_checked_in;
    }

    private function filterByPlayingStatus(PlayerContextDTO $player): bool
    {
        return !$player->is_playing;
    }

    private function filterBySkipStatus(PlayerContextDTO $player): bool
    {
        return !$player->skip_next_round;
    }

    private function filterByForcedRest(PlayerContextDTO $player, bool $preventThreeConsecutive): bool
    {
        if (!$preventThreeConsecutive) {
            return true;
        }

        return $player->consecutive_count < 2;
    }

    private function selectPlayers(array $pool, MatchSuggestionRequestDTO $request): array
    {
        $fresh = array_filter($pool, fn($p) => $p->played_count === 0);
        $justPlayed = array_filter($pool, fn($p) => $p->played_count > 0);

        if ($request->settings->fair_play) {
            $fresh = $this->applyFairPlayPriority($fresh);
            $justPlayed = $this->applyRestPriority($justPlayed);
        }

        $combined = array_merge($fresh, $justPlayed);

        return array_slice($combined, 0, 4);
    }

    private function applyFairPlayPriority(array $players): array
    {
        usort($players, fn($a, $b) => $a->played_count <=> $b->played_count);
        return $players;
    }

    private function applyRestPriority(array $players): array
    {
        usort($players, fn($a, $b) => $b->rest_count <=> $a->rest_count);
        return $players;
    }

    private function canFormHighTierMatch(array $pool): bool
    {
        $tierA = array_filter($pool, fn($p) => $p->tier === MatchTier::A);
        return count($tierA) >= 4;
    }

    /**
     * Thử tạo trận "Căng tay" - ghép cùng tier (A với A, B với B).
     * Ưu tiên ghép A với A trước, rồi mới đến B với B.
     */
    private function tryCreateSameTierMatch(array $pool): ?SuggestionMatchDTO
    {
        $tierAPlayers = array_values(array_filter($pool, fn($p) => $p->tier === MatchTier::A));
        $tierBPlayers = array_values(array_filter($pool, fn($p) => $p->tier === MatchTier::B));

        // Ghép A với A (căng tay)
        if (count($tierAPlayers) >= 4) {
            $tierAPlayers = $this->sortByPartnerHistory($tierAPlayers, $pool);
            $team1Players = array_slice($tierAPlayers, 0, 2);
            $team2Players = array_slice($tierAPlayers, 2, 2);
            return $this->buildMatchDTO($team1Players, $team2Players, [], false);
        }

        // Ghép B với B (căng tay)
        if (count($tierBPlayers) >= 4) {
            $tierBPlayers = $this->sortByPartnerHistory($tierBPlayers, $pool);
            $team1Players = array_slice($tierBPlayers, 0, 2);
            $team2Players = array_slice($tierBPlayers, 2, 2);
            return $this->buildMatchDTO($team1Players, $team2Players, [], false);
        }

        // Không đủ 4 người cùng tier → fallback sang logic khác
        return null;
    }

    private function createHighTierMatch(array $pool, array $userDataMap): SuggestionMatchDTO
    {
        $tierAPlayers = array_filter($pool, fn($p) => $p->tier === MatchTier::A);
        $tierAPlayers = array_values($tierAPlayers);

        $tierAPlayers = $this->sortByPartnerHistory($tierAPlayers, $pool);

        $team1Players = array_slice($tierAPlayers, 0, 2);
        $team2Players = array_slice($tierAPlayers, 2, 2);

        return $this->buildMatchDTO($team1Players, $team2Players, $userDataMap, true);
    }

    private function balanceTeams(array $selectedPlayers, array $userDataMap): SuggestionMatchDTO
    {
        $selectedPlayers = $this->sortByPartnerHistory($selectedPlayers, $selectedPlayers);

        $bestPairing = $this->findOptimalPairing($selectedPlayers);

        $tierAScore = array_sum(array_map(fn($p) => $p->tier === MatchTier::A ? 1 : 0, $bestPairing['team_a']));
        $tierBScore = array_sum(array_map(fn($p) => $p->tier === MatchTier::A ? 1 : 0, $bestPairing['team_b']));

        return $this->buildMatchDTO(
            $bestPairing['team_a'],
            $bestPairing['team_b'],
            $userDataMap,
            $tierAScore >= 2 && $tierBScore >= 2
        );
    }

    private function buildMatchDTO(array $team1Players, array $team2Players, array $userDataMap, bool $isHighTier): SuggestionMatchDTO
    {
        $team1 = new TeamMatchDTO(
            id: null,
            name: 'Team 1',
            members: array_map(fn($p) => $this->buildMemberDTO($p, $userDataMap), $team1Players),
        );

        $team2 = new TeamMatchDTO(
            id: null,
            name: 'Team 2',
            members: array_map(fn($p) => $this->buildMemberDTO($p, $userDataMap), $team2Players),
        );

        return new SuggestionMatchDTO(
            team1: $team1,
            team2: $team2,
            is_high_tier_match: $isHighTier,
        );
    }

    private function buildMemberDTO(PlayerContextDTO $player, array $userDataMap): TeamMatchMemberDTO
    {
        $userData = $userDataMap[$player->user_id] ?? ['visibility' => 'open', 'sports' => []];

        return new TeamMatchMemberDTO(
            id: $player->mini_participant_id,
            user_id: $player->user_id,
            team_id: null,
            full_name: $player->full_name,
            avatar_url: $player->avatar_url,
            is_guest: $player->is_guest,
            visibility: $userData['visibility'] ?? 'open',
            sports: $userData['sports'] ?? [],
            tier: $player->tier->value,
        );
    }

    private function sortByPartnerHistory(array $players, array $allPlayers): array
    {
        $playerMap = [];
        foreach ($allPlayers as $p) {
            $playerMap[$p->user_id] = $p;
        }

        usort($players, function ($a, $b) use ($playerMap, $players) {
            $aPartners = $playerMap[$a->user_id]->partner_ids ?? [];
            $bPartners = $playerMap[$b->user_id]->partner_ids ?? [];
            $playerUserIds = array_column($players, 'user_id');

            $aHasPartnerInPool = count(array_intersect($aPartners, $playerUserIds));
            $bHasPartnerInPool = count(array_intersect($bPartners, $playerUserIds));

            return $aHasPartnerInPool <=> $bHasPartnerInPool;
        });

        return $players;
    }

    private function findOptimalPairing(array $players): array
    {
        if (count($players) < 4) {
            return ['team_a' => $players, 'team_b' => []];
        }

        $teamA = array_slice($players, 0, 2);
        $teamB = array_slice($players, 2, 2);

        $scoreA = $this->calculateTeamScore($teamA);
        $scoreB = $this->calculateTeamScore($teamB);
        $currentDiff = abs($scoreA - $scoreB);

        $userIds = array_column($players, 'user_id');
        $permutations = $this->getPermutations($userIds);

        foreach ($permutations as $perm) {
            $testA = array_slice($perm, 0, 2);
            $testB = array_slice($perm, 2, 2);

            $playersA = array_map(fn($id) => $players[array_search($id, $userIds)], $testA);
            $playersB = array_map(fn($id) => $players[array_search($id, $userIds)], $testB);

            $testScoreA = $this->calculateTeamScore($playersA);
            $testScoreB = $this->calculateTeamScore($playersB);
            $testDiff = abs($testScoreA - $testScoreB);

            if ($testDiff < $currentDiff) {
                $teamA = $playersA;
                $teamB = $playersB;
                $currentDiff = $testDiff;
            }
        }

        return ['team_a' => $teamA, 'team_b' => $teamB];
    }

    private function calculateTeamScore(array $team): int
    {
        return array_sum(array_map(fn($p) => $p->tier === MatchTier::A ? 1 : 0, $team));
    }

    private function buildWaitingList(array $pool, array $excludeIds): array
    {
        return array_values(array_filter($pool, fn($p) => !in_array($p->user_id, $excludeIds)));
    }

    private function getBackupIfNeeded(array $selected, bool $organizerAsBackup): ?PlayerContextDTO
    {
        if (!$organizerAsBackup) {
            return null;
        }

        if (count($selected) < 4) {
            $backups = array_filter($selected, fn($p) => $p->is_backup);
            return !empty($backups) ? reset($backups) : null;
        }

        return null;
    }

    private function calculateStatistics(
        ?SuggestionMatchDTO $match,
        array $selected,
        array $waiting,
        ?PlayerContextDTO $backup
    ): array {
        $playedCounts = array_column($selected, 'played_count');

        $fairnessScore = 1.0;
        if (!empty($playedCounts)) {
            $maxPlayed = max($playedCounts);
            $minPlayed = min($playedCounts);
            $fairnessScore = $maxPlayed > 0 ? 1 - (($maxPlayed - $minPlayed) / max($maxPlayed, 1)) : 1.0;
        }

        $balanceScore = 0.5;
        if ($match) {
            $tierAScore = $this->countTierA($match->team1->members);
            $tierBScore = $this->countTierA($match->team2->members);
            $totalScore = $tierAScore + $tierBScore;
            $balanceScore = $totalScore > 0 ? 1 - abs($tierAScore - $tierBScore) / $totalScore : 0.5;
        }

        return [
            'fairness_score' => round($fairnessScore, 2),
            'balance_score' => round($balanceScore, 2),
            'total_available_players' => count($selected) + count($waiting),
            'selected_count' => count($selected),
            'waiting_count' => count($waiting),
        ];
    }

    private function countTierA(array $members): int
    {
        $count = 0;
        foreach ($members as $m) {
            $member = $m instanceof TeamMatchMemberDTO ? $m : TeamMatchMemberDTO::fromArray($m);
            if ($member->tier === 'A') {
                $count++;
            }
        }
        return $count;
    }

    private function shuffleWithSeed(array $players, ?int $seed): array
    {
        if ($seed === null) {
            shuffle($players);
            return $players;
        }

        $indexes = range(0, count($players) - 1);
        mt_srand($seed);
        shuffle($indexes);
        mt_srand();

        $shuffled = [];
        foreach ($indexes as $i) {
            $shuffled[] = $players[$i];
        }

        return $shuffled;
    }

    private function getPermutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $permutations = [];
        $first = array_shift($items);

        foreach ($this->getPermutations($items) as $perm) {
            for ($i = 0; $i <= count($perm); $i++) {
                $result = array_merge(
                    array_slice($perm, 0, $i),
                    [$first],
                    array_slice($perm, $i)
                );
                $permutations[] = $result;
            }
        }

        return $permutations;
    }

    private function createInsufficientPlayersResponse(
        int $seed,
        array $pool,
        array $rulesApplied,
        array $messages,
        array $userDataMap = []
    ): MatchSuggestionResponseDTO {
        $messages[] = 'Không đủ người chơi (cần ít nhất 4 người)';

        return new MatchSuggestionResponseDTO(
            match: null,
            waiting_players: $pool,
            backup_used: false,
            backup_player: null,
            statistics: [
                'fairness_score' => 0,
                'balance_score' => 0,
                'total_available_players' => count($pool),
                'selected_count' => 0,
                'waiting_count' => count($pool),
            ],
            seed: $seed,
            rules_applied: $rulesApplied,
            messages: $messages,
        );
    }
}
