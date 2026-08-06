<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\PlayerContextDTO;
use App\DTO\SuggestionMatchDTO;
use App\DTO\TeamMatchDTO;
use App\DTO\TeamMatchMemberDTO;
use App\Enums\PlayerTier;
use App\Models\User;

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

        // Build eligible player pool
        $pool = $this->buildPool($players, $request);
        
        if (count($pool) < 4) {
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        // Find gender-compatible player groups and select the best match
        $genderGroups = $this->findGenderCompatibleGroups($pool);
        
        $bestMatch = null;
        $bestScore = PHP_FLOAT_MIN;

        foreach ($genderGroups as $group) {
            // Apply fair play priority to select 4 players
            $selected = $this->selectPlayers($group, $request);
            
            if (count($selected) < 4) {
                continue;
            }

            // Find optimal team split with VN DUPR balance
            $pairing = $this->findOptimalPairing($selected, $userDataMap, $settings);
            
            if (!$pairing) {
                continue;
            }

            // Calculate overall match quality score
            $score = $this->calculateMatchScore($pairing['team_a'], $pairing['team_b'], $settings, $userDataMap);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $this->buildMatchDTO(
                    $pairing['team_a'],
                    $pairing['team_b'],
                    $userDataMap,
                    $pairing['is_high_tier']
                );
                
                if (!empty($pairing['rules_applied'])) {
                    $rulesApplied = array_merge($rulesApplied, $pairing['rules_applied']);
                }
            }
        }

        // Fallback if no valid match found
        if (!$bestMatch) {
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        $selectedIds = array_column($pool, 'user_id');
        $waiting = $this->buildWaitingList($pool, $selectedIds);
        $backup = $this->getBackupIfNeeded($selectedIds, $settings->organizer_as_backup);
        $statistics = $this->calculateStatistics($bestMatch, $pool, $selectedIds);

        return new MatchSuggestionResponseDTO(
            match: $bestMatch,
            waiting_players: $waiting,
            backup_used: $backup !== null,
            backup_player: $backup,
            statistics: $statistics,
            seed: $seed,
            rules_applied: array_unique($rulesApplied),
            messages: $messages,
        );
    }

    /**
     * Build eligible player pool with all filters applied.
     */
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

        // Exclude specified players (from regenerate)
        if ($request->exclude_player_ids) {
            $pool = array_filter($pool, fn($p) => !in_array($p->user_id, $request->exclude_player_ids));
            $pool = array_values($pool);
        }

        // Shuffle with seed for determinism
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

    /**
     * Find all valid gender-compatible groups.
     * Returns array of player arrays, each containing 4+ players.
     * 
     * PRIORITY: Mixed gender (nam nữ) is preferred over same-gender groups.
     */
    private function findGenderCompatibleGroups(array $pool): array
    {
        // Separate players by gender
        $males = array_filter($pool, fn($p) => $p->gender === User::MALE);
        $females = array_filter($pool, fn($p) => $p->gender === User::FEMALE);
        $unknown = array_filter($pool, fn($p) => $p->gender === null || $p->gender === User::OTHER || $p->gender === User::NO_PUBLIC);

        $groups = [];

        // Mixed gender FIRST: need at least 2 males and 2 females for 2v2
        if (count($males) >= 2 && count($females) >= 2) {
            $mixedGroup = array_merge(
                array_slice($males, 0, 4),
                array_slice($females, 0, 4)
            );
            $groups[] = $mixedGroup;
        }
        
        // Only allow same-gender groups if mixed is not possible
        // Check if we already have mixed gender - if yes, skip same-gender
        if (!empty($groups)) {
            return $groups;
        }

        // All male: fallback only if mixed not possible
        if (count($males) >= 4) {
            $groups[] = array_values($males);
        }

        // All female: fallback only if mixed not possible
        if (count($females) >= 4) {
            $groups[] = array_values($females);
        }

        // Unknown gender: fallback if no gender-compatible groups
        if (empty($groups) && count($unknown) >= 4) {
            $groups[] = array_values($unknown);
        }

        // Last resort: use all available players
        if (empty($groups) && count($pool) >= 4) {
            $groups[] = $pool;
        }

        return $groups;
    }

    /**
     * Select 4 players using fair play priority.
     * Priority: played_count (ascending) > waiting_rounds (descending) > tier priority
     * 
     * For mixed gender groups, ensures 2-2 split.
     */
    private function selectPlayers(array $players, MatchSuggestionRequestDTO $request): array
    {
        // Count genders
        $males = array_filter($players, fn($p) => $p->gender === User::MALE);
        $females = array_filter($players, fn($p) => $p->gender === User::FEMALE);
        
        $isMixedGroup = count($males) >= 2 && count($females) >= 2;
        
        if ($isMixedGroup) {
            // Mixed gender: select 2 males + 2 females with fair play priority
            $freshMales = array_filter($males, fn($p) => $p->played_count === 0);
            $freshFemales = array_filter($females, fn($p) => $p->played_count === 0);
            $playedMales = array_filter($males, fn($p) => $p->played_count > 0);
            $playedFemales = array_filter($females, fn($p) => $p->played_count > 0);

            if ($request->settings->fair_play) {
                $freshMales = $this->applyFairPlayPriority($freshMales);
                $freshFemales = $this->applyFairPlayPriority($freshFemales);
                $playedMales = $this->applyRestPriority($playedMales);
                $playedFemales = $this->applyRestPriority($playedFemales);
            }

            // Take 2 from each gender
            $selected = array_merge(
                array_slice($freshMales, 0, 2),
                array_slice($freshFemales, 0, 2)
            );

            // Fill with played if needed
            if (count($selected) < 4) {
                $remainingMales = array_slice($playedMales, 0, 4 - count($selected));
                $remainingFemales = array_slice($playedFemales, 0, 4 - count($selected));
                $selected = array_merge($selected, $remainingMales, $remainingFemales);
            }

            return array_slice($selected, 0, 4);
        }

        // Same gender or unknown: use original logic
        $fresh = array_filter($players, fn($p) => $p->played_count === 0);
        $justPlayed = array_filter($players, fn($p) => $p->played_count > 0);

        if ($request->settings->fair_play) {
            $fresh = $this->applyFairPlayPriority($fresh);
            $justPlayed = $this->applyRestPriority($justPlayed);
        }

        $combined = array_merge($fresh, $justPlayed);

        return array_slice($combined, 0, 4);
    }

    /**
     * Apply fair play priority: players with fewer matches play first.
     */
    private function applyFairPlayPriority(array $players): array
    {
        usort($players, function ($a, $b) {
            // Primary: fewer matches played
            if ($a->played_count !== $b->played_count) {
                return $a->played_count <=> $b->played_count;
            }
            // Secondary: more waiting rounds
            if ($a->waiting_rounds !== $b->waiting_rounds) {
                return $b->waiting_rounds <=> $a->waiting_rounds;
            }
            // Tertiary: lower tier (green before yellow before red before purple)
            // This prevents tier starvation - lower tiers get priority
            if ($a->tier->priority() !== $b->tier->priority()) {
                return $a->tier->priority() <=> $b->tier->priority();
            }
            return 0;
        });
        return $players;
    }

    /**
     * Apply rest priority: players who rested longer play first.
     */
    private function applyRestPriority(array $players): array
    {
        usort($players, function ($a, $b) {
            // Primary: more waiting rounds
            if ($a->waiting_rounds !== $b->waiting_rounds) {
                return $b->waiting_rounds <=> $a->waiting_rounds;
            }
            // Secondary: fewer matches played
            if ($a->played_count !== $b->played_count) {
                return $a->played_count <=> $b->played_count;
            }
            // Tertiary: lower tier
            if ($a->tier->priority() !== $b->tier->priority()) {
                return $a->tier->priority() <=> $b->tier->priority();
            }
            return 0;
        });
        return $players;
    }

    /**
     * Find optimal team pairing using VN DUPR scores.
     * Evaluates all possible combinations and selects the most balanced one.
     * 
     * @return array|null ['team_a' => PlayerContextDTO[], 'team_b' => PlayerContextDTO[], 'is_high_tier' => bool, 'rules_applied' => string[]]
     */
    private function findOptimalPairing(array $players, array $userDataMap, $settings): ?array
    {
        if (count($players) < 4) {
            return null;
        }

        // Get gender info for validation
        $genderCounts = $this->countGenders($players);
        
        // Try all permutations
        $userIds = array_column($players, 'user_id');
        $permutations = $this->getPermutations($userIds);
        
        $bestPairing = null;
        $bestBalanceDiff = PHP_FLOAT_MAX;

        foreach ($permutations as $perm) {
            $teamAIds = array_slice($perm, 0, 2);
            $teamBIds = array_slice($perm, 2, 2);

            $playersA = $this->getPlayersByIds($teamAIds, $players);
            $playersB = $this->getPlayersByIds($teamBIds, $players);

            // Validate gender compatibility
            if (!$this->isValidGenderPairing($playersA, $playersB, $genderCounts)) {
                continue;
            }

            // Calculate VN DUPR balance
            $balanceDiff = $this->calculateBalanceDiff($playersA, $playersB, $userDataMap);

            if ($balanceDiff < $bestBalanceDiff) {
                $bestBalanceDiff = $balanceDiff;
                $bestPairing = [
                    'team_a' => $playersA,
                    'team_b' => $playersB,
                    'is_high_tier' => $this->isHighTierMatch($playersA, $playersB),
                    'rules_applied' => [],
                ];
            }
        }

        // Mark rules applied
        if ($bestPairing && $bestPairing['is_high_tier'] && $settings->prefer_high_tier_match) {
            $bestPairing['rules_applied'][] = 'prefer_high_tier_match';
        }

        if ($bestPairing && $settings->balance_team) {
            $bestPairing['rules_applied'][] = 'balance_team';
        }

        return $bestPairing;
    }

    /**
     * Count genders in player array.
     */
    private function countGenders(array $players): array
    {
        $counts = [
            User::MALE => 0,
            User::FEMALE => 0,
            'unknown' => 0,
        ];

        foreach ($players as $p) {
            if ($p->gender === User::MALE) {
                $counts[User::MALE]++;
            } elseif ($p->gender === User::FEMALE) {
                $counts[User::FEMALE]++;
            } else {
                $counts['unknown']++;
            }
        }

        return $counts;
    }

    /**
     * Check if a team pairing respects gender rules.
     * Mixed gender must be symmetric (2-2 or 1-1).
     */
    private function isValidGenderPairing(array $teamA, array $teamB, array $genderCounts): bool
    {
        $allPlayers = array_merge($teamA, $teamB);
        
        $hasMale = false;
        $hasFemale = false;

        foreach ($allPlayers as $p) {
            if ($p->gender === User::MALE) $hasMale = true;
            if ($p->gender === User::FEMALE) $hasFemale = true;
        }

        // If we have mixed gender, require symmetric distribution
        if ($hasMale && $hasFemale) {
            $malesInA = count(array_filter($teamA, fn($p) => $p->gender === User::MALE));
            $malesInB = count(array_filter($teamB, fn($p) => $p->gender === User::MALE));
            $femalesInA = count(array_filter($teamA, fn($p) => $p->gender === User::FEMALE));
            $femalesInB = count(array_filter($teamB, fn($p) => $p->gender === User::FEMALE));

            // Mixed must be symmetric: 2 males + 2 females
            return $malesInA === $femalesInA && $malesInB === $femalesInB;
        }

        // All-male or all-female is always valid
        return true;
    }

    /**
     * Calculate balance difference between two teams using VN DUPR scores.
     * Returns absolute difference in team strength.
     */
    private function calculateBalanceDiff(array $teamA, array $teamB, array $userDataMap): float
    {
        $strengthA = $this->calculateTeamStrength($teamA, $userDataMap);
        $strengthB = $this->calculateTeamStrength($teamB, $userDataMap);

        return abs($strengthA - $strengthB);
    }

    /**
     * Calculate team strength based on VN DUPR scores.
     * Falls back to tier score if VN DUPR not available.
     */
    private function calculateTeamStrength(array $players, array $userDataMap): float
    {
        $total = 0;
        $count = 0;

        foreach ($players as $p) {
            if (!$p->user_id) {
                // Guest without user: use tier score
                $total += $p->tier->score();
                $count++;
                continue;
            }

            $userData = $userDataMap[$p->user_id] ?? null;
            
            if ($userData && !empty($userData['sports'])) {
                $vndupr = $userData['sports'][0]['scores']['vndupr_score'] ?? null;
                if ($vndupr !== null && is_numeric($vndupr)) {
                    $total += (float) $vndupr;
                    $count++;
                } else {
                    // Fallback to tier score
                    $total += $p->tier->score();
                    $count++;
                }
            } else {
                // No VN DUPR data: use tier score
                $total += $p->tier->score();
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0;
    }

    /**
     * Check if this is a high-tier match (prefer_high_tier_match setting).
     */
    private function isHighTierMatch(array $teamA, array $teamB): bool
    {
        $allPlayers = array_merge($teamA, $teamB);
        
        // A "high tier" match has average tier >= Yellow (2.0)
        $totalTierScore = array_sum(array_map(fn($p) => $p->tier->score(), $allPlayers));
        $avgTier = $totalTierScore / count($allPlayers);

        return $avgTier >= 2.0; // Yellow or higher
    }

    /**
     * Calculate overall match quality score.
     * Lower is better (balance diff).
     */
    private function calculateMatchScore(array $teamA, array $teamB, $settings, array $userDataMap): float
    {
        // Score = negative balance diff (lower is better)
        // Higher returned value = better match
        $balanceDiff = $this->calculateBalanceDiff($teamA, $teamB, $userDataMap);
        
        // Normalize: subtract from a large number so higher = better
        return 1000 - $balanceDiff;
    }

    /**
     * Get players by their user IDs.
     */
    private function getPlayersByIds(array $userIds, array $players): array
    {
        $playerMap = [];
        foreach ($players as $p) {
            $playerMap[$p->user_id] = $p;
        }

        $result = [];
        foreach ($userIds as $userId) {
            if (isset($playerMap[$userId])) {
                $result[] = $playerMap[$userId];
            }
        }

        return $result;
    }

    /**
     * Build match DTO from selected players.
     */
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

    /**
     * Build member DTO from player context.
     */
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

    /**
     * Build waiting list (players not selected for current match).
     */
    private function buildWaitingList(array $pool, array $excludeIds): array
    {
        return array_values(array_filter($pool, fn($p) => !in_array($p->user_id, $excludeIds)));
    }

    /**
     * Get backup player if needed.
     */
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

    /**
     * Calculate statistics for the generated match.
     */
    private function calculateStatistics(?SuggestionMatchDTO $match, array $pool, array $selectedIds): array
    {
        $selected = array_filter($pool, fn($p) => in_array($p->user_id, $selectedIds));
        $waiting = array_filter($pool, fn($p) => !in_array($p->user_id, $selectedIds));

        $playedCounts = array_column($selected, 'played_count');

        $fairnessScore = 1.0;
        if (!empty($playedCounts)) {
            $maxPlayed = max($playedCounts);
            $minPlayed = min($playedCounts);
            $fairnessScore = $maxPlayed > 0 ? 1 - (($maxPlayed - $minPlayed) / max($maxPlayed, 1)) : 1.0;
        }

        $balanceScore = 0.5;
        if ($match) {
            $totalPlayers = count($match->team1->members) + count($match->team2->members);
            $balanceScore = $totalPlayers > 0 ? 0.5 : 0.5; // Simplified - can be enhanced
        }

        return [
            'fairness_score' => round($fairnessScore, 2),
            'balance_score' => round($balanceScore, 2),
            'total_available_players' => count($pool),
            'selected_count' => count($selected),
            'waiting_count' => count($waiting),
        ];
    }

    /**
     * Shuffle array deterministically using seed.
     */
    private function shuffleWithSeed(array $players, ?int $seed): array
    {
        if (empty($players)) {
            return $players;
        }

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
            if (isset($players[$i])) {
                $shuffled[] = $players[$i];
            }
        }

        return $shuffled;
    }

    /**
     * Generate all permutations of an array.
     */
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

    /**
     * Create response for insufficient players.
     */
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
