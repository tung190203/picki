<?php

namespace App\Services;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\PlayerContextDTO;
use App\DTO\SuggestionMatchDTO;
use App\DTO\TeamMatchDTO;
use App\DTO\TeamMatchMemberDTO;
use App\Enums\PaymentStatusEnum;
use App\Enums\PlayerTier;
use App\Models\User;

class SchedulerService
{
    /**
     * @param array $players PlayerContextDTO[]
     * @param MatchSuggestionRequestDTO $request
     * @param array $userDataMap [user_id => ['visibility' => string, 'sports' => array]]
     * @param bool $needsPaymentCheck if true, exclude participants with unpaid status
    */
    public function generate(
        array $players,
        MatchSuggestionRequestDTO $request,
        array $userDataMap = [],
        bool $needsPaymentCheck = false
    ): MatchSuggestionResponseDTO {
        $seed = $request->seed ?? random_int(1, 999999);
        $rulesApplied = [];
        $messages = [];
        $settings = $request->settings;

        // Build eligible player pool
        $pool = $this->buildPool($players, $request, $needsPaymentCheck);
        
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
    private function buildPool(array $players, MatchSuggestionRequestDTO $request, bool $needsPaymentCheck): array
    {
        $pool = [];

        foreach ($players as $player) {
            if (!$this->filterByAvailability($player, $needsPaymentCheck)) continue;
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

    /**
     * Filter player by availability: not absent, and paid if tournament has fee.
     * Replaces filterByCheckIn which only checked is_checked_in.
     */
    private function filterByAvailability(PlayerContextDTO $player, bool $needsPaymentCheck): bool
    {
        // Exclude absent players
        if ($player->is_absent) {
            return false;
        }

        // If tournament has fee that requires payment, exclude unpaid participants
        if ($needsPaymentCheck && $player->payment_status !== null) {
            if ($player->payment_status !== PaymentStatusEnum::CONFIRMED->value) {
                return false;
            }
        }

        return true;
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
     * For mixed gender, also considers tier distribution to avoid imbalanced groups.
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
            // Sort by tier for better selection (high priority tiers first)
            $sortedMales = $this->sortByTierForBalance($males);
            $sortedFemales = $this->sortByTierForBalance($females);

            // If we have many players, try to find the best combination for tier balance
            if (count($males) > 4 || count($females) > 4) {
                $mixedGroup = $this->buildBalancedMixedGroup($sortedMales, $sortedFemales);
            } else {
                // Just take all available
                $mixedGroup = array_merge(
                    array_slice($sortedMales, 0, 4),
                    array_slice($sortedFemales, 0, 4)
                );
            }
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
     * Build a balanced mixed-gender group considering tier distribution.
     * Tries to include a mix of tiers rather than clustering all high/low tiers together.
     */
    private function buildBalancedMixedGroup(array $males, array $females): array
    {
        $selected = [];

        // Take up to 4 from each gender
        $maleCount = min(4, count($males));
        $femaleCount = min(4, count($females));

        // Interleave: take highest tier from one gender, then highest from other
        // This ensures we don't cluster all high-tier in one gender
        $maleIdx = 0;
        $femaleIdx = 0;

        // First, collect 2 from each gender prioritizing tier mix
        $selectedMales = [];
        $selectedFemales = [];

        for ($i = 0; $i < $maleCount; $i++) {
            if ($maleIdx < count($males)) {
                $selectedMales[] = $males[$maleIdx++];
            }
        }

        for ($i = 0; $i < $femaleCount; $i++) {
            if ($femaleIdx < count($females)) {
                $selectedFemales[] = $females[$femaleIdx++];
            }
        }

        return array_merge($selectedMales, $selectedFemales);
    }

    /**
     * Select 4 players using fair play priority.
     * Priority: played_count (ascending) > waiting_rounds (descending) > tier priority
     * 
     * For mixed gender groups, ensures 2-2 split and tier balance.
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

            // If we have enough fresh players, use tier balance selection
            // This ensures we pick a mix of tiers for better pairing later
            if (count($freshMales) >= 2 && count($freshFemales) >= 2) {
                // Sort by tier (high priority first) for tier balance selection
                usort($freshMales, fn($a, $b) => $b->tier->priority() - $a->tier->priority());
                usort($freshFemales, fn($a, $b) => $b->tier->priority() - $a->tier->priority());

                $selected = $this->selectForTierBalance($freshMales, $freshFemales, 2);

                // If we still need players, fill from played
                if (count($selected) < 4) {
                    $remaining = array_slice(array_merge($playedMales, $playedFemales), 0, 4 - count($selected));
                    $selected = array_merge($selected, $remaining);
                }

                return array_slice($selected, 0, 4);
            }

            // Fallback: take 2 from each gender (original logic)
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
     * Find optimal team pairing using VN DUPR scores and tier distribution.
     * Evaluates all possible combinations and selects the most balanced one.
     * 
     * When prefer_high_tier_match is enabled, prioritizes:
     * 1. Same tier in each team (e.g., đỏ đỏ vs đỏ đỏ)
     * 2. Corresponding tier distribution (e.g., xanh đỏ vs xanh đỏ)
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
        $bestTierScore = -1.0;      // Tier score cao nhất tìm được
        $bestBalanceDiff = PHP_FLOAT_MAX;
        $preferHighTier = $settings->prefer_high_tier_match ?? false;

        foreach ($permutations as $perm) {
            $teamAIds = array_slice($perm, 0, 2);
            $teamBIds = array_slice($perm, 2, 2);

            $playersA = $this->getPlayersByIds($teamAIds, $players);
            $playersB = $this->getPlayersByIds($teamBIds, $players);

            // Validate gender compatibility
            if (!$this->isValidGenderPairing($playersA, $playersB, $genderCounts)) {
                continue;
            }

            // Calculate tier distribution score (only when preferHighTier is enabled)
            $tierScore = $preferHighTier 
                ? $this->calculateTierDistributionMatch($playersA, $playersB)
                : 0.0;

            // Calculate VN DUPR balance
            $balanceDiff = $this->calculateBalanceDiff($playersA, $playersB, $userDataMap);

            // 2-LEVEL COMPARISON: Tier first, then VN DUPR as tiebreaker
            $shouldUpdate = false;

            if ($bestPairing === null) {
                $shouldUpdate = true;
            } else {
                // Ưu tiên 1: Tier score cao hơn
                if ($tierScore > $bestTierScore) {
                    $shouldUpdate = true;
                }
                // Ưu tiên 2: Balance diff thấp hơn (chỉ khi tier bằng nhau)
                elseif ($tierScore === $bestTierScore && $balanceDiff < $bestBalanceDiff) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                $bestTierScore = $tierScore;
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
        if ($bestPairing && $bestPairing['is_high_tier'] && $preferHighTier) {
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
     * Calculate tier distribution match score for prefer_high_tier_match setting.
     * 
     * Returns:
     * - 3.0: Perfect same-tier match - both teams have same internal tier (e.g., [C,C] vs [C,C])
     * - 2.0: Perfect distribution - same tier distribution after sorting (e.g., [A,B] vs [A,B])
     * - 0.0: Mismatched distribution (e.g., [A,A] vs [B,B])
     * 
     * When prefer_high_tier_match is enabled, algorithm prioritizes:
     * 1. Same tier in each team (e.g., đỏ đỏ vs đỏ đỏ)
     * 2. If not enough, corresponding distribution (e.g., xanh đỏ vs xanh đỏ)
     * 3. Internal mismatch penalty: both teams have same internal tier but DIFFERENT between teams
     */
    private function calculateTierDistributionMatch(array $teamA, array $teamB): float
    {
        $tiersA = array_map(fn($p) => $p->tier->priority(), $teamA);
        $tiersB = array_map(fn($p) => $p->tier->priority(), $teamB);
        
        // Check if each team has same internal tier (e.g., [2,2] or [3,3])
        $sameTierA = $tiersA[0] === $tiersA[1];
        $sameTierB = $tiersB[0] === $tiersB[1];
        
        // Case 1: Perfect match - both teams have same internal tier AND they match each other
        // e.g., Team A [đỏ,đỏ] and Team B [đỏ,đỏ] = 3.0
        if ($sameTierA && $sameTierB && $tiersA[0] === $tiersB[0]) {
            return 3.0;
        }
        
        // Case 2: Same distribution after sorting
        // e.g., Team A [xanh,đỏ] = [1,3] and Team B [xanh,đỏ] = [1,3] = 2.0
        $sortedA = $tiersA;
        $sortedB = $tiersB;
        sort($sortedA);
        sort($sortedB);
        if ($sortedA === $sortedB) {
            return 2.0;
        }
        
        // Case 3: Both teams have same internal tier but DIFFERENT between teams
        // e.g., Team A [vàng,vàng] and Team B [đỏ,đỏ] = 1.0 (PENALTY)
        if ($sameTierA && $sameTierB && $tiersA[0] !== $tiersB[0]) {
            return 1.0;
        }
        
        // Case 4: All other cases = 0.0
        return 0.0;
    }

    /**
     * Calculate overall match quality score.
     * Lower balance diff is better (higher returned value).
     * When prefer_high_tier_match is enabled, also considers tier distribution.
     */
    private function calculateMatchScore(array $teamA, array $teamB, $settings, array $userDataMap): float
    {
        $balanceDiff = $this->calculateBalanceDiff($teamA, $teamB, $userDataMap);
        
        // When prefer_high_tier_match is enabled, add tier matching bonus
        $tierBonus = 0;
        if ($settings->prefer_high_tier_match ?? false) {
            $tierMatch = $this->calculateTierDistributionMatch($teamA, $teamB);
            $tierBonus = $tierMatch * 10;
        }
        
        // Normalize: subtract from a large number so higher = better
        return 1000 - $balanceDiff + $tierBonus;
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

    /**
     * Calculate how balanced a group of players is in terms of tier distribution.
     * Returns a score where HIGHER = more balanced.
     */
    private function calculatePoolTierBalance(array $players): float
    {
        $tierCounts = [];
        foreach ($players as $p) {
            $tierName = $p->tier->name;
            $tierCounts[$tierName] = ($tierCounts[$tierName] ?? 0) + 1;
        }

        if (count($tierCounts) <= 1) {
            return 10.0; // All same tier = perfectly balanced
        }

        // Calculate variance of tier counts (lower variance = more balanced)
        $counts = array_values($tierCounts);
        $avg = array_sum($counts) / count($counts);
        $variance = 0;
        foreach ($counts as $c) {
            $variance += pow($c - $avg, 2);
        }

        // Higher score = more balanced (lower variance)
        return max(0, 10 - sqrt($variance));
    }

    /**
     * Sort players by tier for better team building.
     * Prioritize mixing different tiers rather than clustering same tier.
     */
    private function sortByTierForBalance(array $players): array
    {
        usort($players, fn($a, $b) => $a->tier->priority() - $b->tier->priority());
        return $players;
    }

    /**
     * Select players ensuring tier balance between genders.
     * Prioritizes picking a mix of high/low tiers to enable better pairing later.
     */
    private function selectForTierBalance(array $males, array $females, int $count): array
    {
        // Group players by tier for each gender
        $malesByTier = [];
        foreach ($males as $m) {
            $tierKey = strtolower($m->tier->name);
            $malesByTier[$tierKey][] = $m;
        }
        $femalesByTier = [];
        foreach ($females as $f) {
            $tierKey = strtolower($f->tier->name);
            $femalesByTier[$tierKey][] = $f;
        }

        // Get unique tiers sorted by priority (high to low)
        $maleTiers = array_keys($malesByTier);
        $femaleTiers = array_keys($femalesByTier);
        usort($maleTiers, fn($a, $b) => PlayerTier::from($a)->priority() - PlayerTier::from($b)->priority());
        usort($femaleTiers, fn($a, $b) => PlayerTier::from($a)->priority() - PlayerTier::from($b)->priority());

        $selected = [];
        $maleIdx = 0;
        $femaleIdx = 0;

        // Pick 1 high-tier and 1 low-tier from each gender when possible
        // This creates better tier distribution for pairing later
        for ($i = 0; $i < $count; $i++) {
            // Even iteration: pick highest available tier from males, lowest from females
            // Odd iteration: pick lowest from males, highest from females
            if ($i % 2 === 0) {
                // Pick male from high tier
                if ($maleIdx < count($maleTiers)) {
                    $tierName = $maleTiers[$maleIdx];
                    if (!empty($malesByTier[$tierName])) {
                        $selected[] = array_shift($malesByTier[$tierName]);
                        if (empty($malesByTier[$tierName])) {
                            $maleIdx++;
                        }
                    }
                }
                // Pick female from low tier (reverse order)
                $femalePickIdx = count($femaleTiers) - 1 - $femaleIdx;
                if ($femalePickIdx >= 0 && $femalePickIdx < count($femaleTiers)) {
                    $tierName = $femaleTiers[$femalePickIdx];
                    if (!empty($femalesByTier[$tierName])) {
                        $selected[] = array_shift($femalesByTier[$tierName]);
                        if (empty($femalesByTier[$tierName])) {
                            unset($femaleTiers[$femalePickIdx]);
                            $femaleTiers = array_values($femaleTiers);
                        }
                    }
                }
            } else {
                // Pick female from high tier
                if ($femaleIdx < count($femaleTiers)) {
                    $tierName = $femaleTiers[$femaleIdx];
                    if (!empty($femalesByTier[$tierName])) {
                        $selected[] = array_shift($femalesByTier[$tierName]);
                        if (empty($femalesByTier[$tierName])) {
                            array_splice($femaleTiers, $femaleIdx, 1);
                        }
                    }
                }
                // Pick male from low tier (reverse order)
                $malePickIdx = count($maleTiers) - 1 - $maleIdx;
                if ($malePickIdx >= 0 && $malePickIdx < count($maleTiers)) {
                    $tierName = $maleTiers[$malePickIdx];
                    if (!empty($malesByTier[$tierName])) {
                        $selected[] = array_shift($malesByTier[$tierName]);
                        if (empty($malesByTier[$tierName])) {
                            unset($maleTiers[$malePickIdx]);
                            $maleTiers = array_values($maleTiers);
                        }
                    }
                }
            }
        }

        return $selected;
    }
}
