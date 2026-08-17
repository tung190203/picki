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
     * Generate match suggestion using combination-based algorithm.
     *
     * NEW ALGORITHM: No anchor constraint. Evaluates all valid 4-player
     * combinations and selects the best by business priority.
     *
     * @param array $players PlayerContextDTO[]
     * @param MatchSuggestionRequestDTO $request
     * @param array $userDataMap [user_id => ['visibility' => string, 'sports' => array]]
     * @param bool $needsPaymentCheck if true, exclude participants with unpaid status
     * @param array $excludeSignatures Signatures to exclude (existing matches)
     */
    public function generate(
        array $players,
        MatchSuggestionRequestDTO $request,
        array $userDataMap = [],
        bool $needsPaymentCheck = false,
        bool $isRotateCall = false,
        array $excludeSignatures = [],
    ): MatchSuggestionResponseDTO {
        $seed = $request->seed ?? random_int(1, 999999);
        $rulesApplied = [];
        $messages = [];
        $settings = $request->settings;

        // Build eligible player pool
        $pool = $this->buildPool($players, $request, $needsPaymentCheck);

        if (count($pool) < 4) {
            $messages[] = 'Pool has less than 4 players after filters: ' . count($pool);
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        // Generate candidates using combination-based algorithm
        $candidates = $this->generateCandidates($pool, $request, $userDataMap, $excludeSignatures);

        if (empty($candidates)) {
            $messages[] = 'No valid match found';
            return $this->createInsufficientPlayersResponse($seed, $pool, $rulesApplied, $messages, $userDataMap);
        }

        // Select best candidate (already sorted by business priority)
        $best = $candidates[0];

        $teamA = $best['team_a'];
        $teamB = $best['team_b'];

        // Validate used_backup correctly - check if any selected player is backup
        $usedBackup = false;
        foreach (array_merge($teamA, $teamB) as $p) {
            if ($p->is_backup) {
                $usedBackup = true;
                break;
            }
        }

        // Add rules applied
        $rulesApplied = $best['rules_applied'] ?? [];
        if ($settings->fair_play) {
            $rulesApplied[] = 'fair_play';
        }
        if ($usedBackup) {
            $rulesApplied[] = 'organizer_as_backup';
        }

        $bestMatch = $this->buildMatchDTO(
            $teamA,
            $teamB,
            $userDataMap,
            $this->isHighTierMatch($teamA, $teamB),
        );

        $selectedIds = array_column(array_merge($teamA, $teamB), 'user_id');
        $selectedMiniParticipantIds = array_column(array_merge($teamA, $teamB), 'mini_participant_id');
        $waiting = $this->buildWaitingList($pool, $selectedIds, $selectedMiniParticipantIds);
        $backup = $this->getBackupIfNeeded($selectedIds, $settings->organizer_as_backup);
        $statistics = $this->calculateStatistics($bestMatch, $pool, $selectedIds, $selectedMiniParticipantIds);

        return new MatchSuggestionResponseDTO(
            match: $bestMatch,
            waiting_players: $waiting,
            backup_used: $usedBackup,
            backup_player: $backup,
            statistics: $statistics,
            seed: $seed,
            rules_applied: array_values(array_unique($rulesApplied)),
            messages: $messages,
            total_candidates: count($candidates),
            selected_offset: 0,
            wrapped: false,
        );
    }

    /**
     * Enumerate all valid match candidates for the given pool.
     *
     * NEW ALGORITHM: Uses combination-based candidate generation.
     * NO anchor constraint - evaluates all valid 4-player combinations.
     *
     * Returns an associative array:
     *   - candidates: list of candidates sorted by business priority
     *   - total_candidates: total unique candidates
     *
     * `signature` is a sorted list of the 4 user_ids (used by regenerate()
     * to skip already-tried combos).
     *
     * @param array $excludeSignatures Signatures to exclude (existing matches)
     * @return array{candidates: array<int, array>, total_candidates: int}
     */
    public function enumerateCandidates(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap = [],
        array $excludeSignatures = [],
    ): array {
        // Generate all valid candidates using the new combination-based algorithm
        $candidates = $this->generateCandidates($pool, $request, $userDataMap, $excludeSignatures);

        // Add is_high_tier and rules_applied to each candidate for backward compatibility
        foreach ($candidates as &$candidate) {
            $candidate['is_high_tier'] = $this->isHighTierMatch($candidate['team_a'], $candidate['team_b']);

            $rulesApplied = $candidate['rules_applied'] ?? [];
            if ($request->settings->fair_play) {
                $rulesApplied[] = 'fair_play';
            }
            if ($candidate['used_backup']) {
                $rulesApplied[] = 'organizer_as_backup';
            }
            $candidate['rules_applied'] = array_values(array_unique($rulesApplied));

            // Add legacy fields for backward compatibility
            $candidate['score'] = $this->calculateMatchScore(
                $candidate['team_a'],
                $candidate['team_b'],
                $request->settings,
                $userDataMap
            );
            $candidate['adjusted_score'] = $candidate['score'];
        }

        return [
            'candidates' => $candidates,
            'total_candidates' => count($candidates),
        ];
    }

    /**
     * Build a stable signature (sorted user_ids) from a candidate's two teams.
     *
     * @return array<int>
     */
    public function buildCandidateSignature(array $teamA, array $teamB): array
    {
        // Use mini_participant_id for both guests and non-guests so signature
        // is stable across renders (guest has null user_id).
        $ids = array_merge(array_column($teamA, 'mini_participant_id'), array_column($teamB, 'mini_participant_id'));
        $ids = array_values(array_map('intval', array_filter($ids, fn($v) => $v !== null)));
        sort($ids);
        return $ids;
    }

    /**
     * Generate all valid 4-player combinations sorted by business priority.
     *
     * THIS IS THE NEW CORE ALGORITHM - NO ANCHOR CONSTRAINT.
     *
     * Flow:
     * 1. Generate same-gender candidates (priority)
     * 2. If none exist, generate mixed-gender candidates
     * 3. If still none, try with backup players
     * 4. Filter out signatures that already exist (excludeSignatures)
     * 5. Return all candidates sorted by business priority
     *
     * @param array $pool PlayerContextDTO[]
     * @param MatchSuggestionRequestDTO $request
     * @param array $userDataMap
     * @param array $excludeSignatures Array of signatures to exclude (already played/pending matches)
     * @return array Candidates with full metadata
     */
    private int $currentPoolMaxPlayed = 0;
    private ?int $currentAnchorId = null;

    public function generateCandidates(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
        array $excludeSignatures = [],
    ): array {
        $settings = $request->settings;

        // Split main and backup pools
        [$mainPool, $backupPool] = $this->splitMainAndBackupPool($pool);

        // Compute pool-wide max_played for starvation-aware fairness comparison
        $this->currentPoolMaxPlayed = $mainPool
            ? max(array_column($mainPool, 'played_count'))
            : 0;

        // Store anchor for compareCandidates tiebreaker
        $this->currentAnchorId = $request->anchor_user_id;

        $candidates = [];

        // STEP 1: Generate same-gender candidates from main pool
        $sameGenderCandidates = $this->generateSameGenderCandidates($mainPool, $request, $userDataMap);
        $candidates = array_merge($candidates, $sameGenderCandidates);

        // STEP 2: Generate mixed-gender candidates from main pool
        // (always run — even if same-gender succeeded, mixed-gender may be better by fairness)
        $mixedCandidates = $this->generateMixedGenderCandidates($mainPool, $request, $userDataMap);
        $candidates = array_merge($candidates, $mixedCandidates);

        // STEP 3: Last resort — any 4 players from main pool, no gender/tier restrictions.
        // Guarantees a match when at least 4 eligible players remain but gender rules block both.
        $anyCandidates = $this->generateAnyTierCombinations($mainPool, $request, $userDataMap, true);
        foreach ($anyCandidates as &$c) {
            $c['is_high_tier'] = $this->isHighTierMatch($c['team_a'], $c['team_b']);
            $c['score'] = $this->calculateMatchScore($c['team_a'], $c['team_b'], $request->settings, $userDataMap);
            $c['adjusted_score'] = $c['score'];
        }
        $candidates = array_merge($candidates, $anyCandidates);

        // STEP 4: If organizer_as_backup is enabled, also try with backup pool
        if ($settings->organizer_as_backup && !empty($backupPool)) {
            $extendedPool = array_merge($mainPool, $backupPool);
            $this->currentPoolMaxPlayed = max(array_column($extendedPool, 'played_count'));

            $extSameGender = $this->generateSameGenderCandidates($extendedPool, $request, $userDataMap);
            $candidates = array_merge($candidates, $extSameGender);

            $extMixed = $this->generateMixedGenderCandidates($extendedPool, $request, $userDataMap);
            $candidates = array_merge($candidates, $extMixed);

            $extAny = $this->generateAnyTierCombinations($extendedPool, $request, $userDataMap, true);
            foreach ($extAny as &$c) {
                $c['is_high_tier'] = $this->isHighTierMatch($c['team_a'], $c['team_b']);
                $c['score'] = $this->calculateMatchScore($c['team_a'], $c['team_b'], $request->settings, $userDataMap);
                $c['adjusted_score'] = $c['score'];
            }
            $candidates = array_merge($candidates, $extAny);
        }

        // STEP 5: Sort by business priority
        usort($candidates, fn($a, $b) => $this->compareCandidates($a, $b));

        // STEP 6: Filter out existing signatures AND deduplicate
        $excludeKeys = [];
        foreach ($excludeSignatures as $sig) {
            $excludeKeys[implode(',', $sig)] = true;
        }

        $seen = [];
        $unique = [];
        foreach ($candidates as $c) {
            $key = implode(',', $c['signature']);

            // Skip if already exists in matches
            if (isset($excludeKeys[$key])) {
                continue;
            }

            // Skip duplicates within candidates
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $c;
            }
        }

        return $unique;
    }

    /**
     * Generate same-gender candidate combinations.
     * Priority: 4 same tier > 4 from adjacent tiers > mixed tiers
     *
     * Handles null gender by treating all null-gender players as one group.
     */
    private function generateSameGenderCandidates(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): array {
        $candidates = [];

        // Separate by gender - handle null gender as unknown group
        $males = [];
        $females = [];
        $unknownGender = [];

        foreach ($pool as $p) {
            if ($p->gender === User::MALE) {
                $males[] = $p;
            } elseif ($p->gender === User::FEMALE) {
                $females[] = $p;
            } else {
                $unknownGender[] = $p;
            }
        }

        // If we have players with unknown gender, treat them as one group
        // so we can form same-gender matches with mixed known/unknown genders
        $genderGroups = [];

        // Males with unknown gender
        $malesWithUnknown = array_merge($males, $unknownGender);
        if (count($malesWithUnknown) >= 4) {
            $genderGroups[] = $malesWithUnknown;
        } elseif (count($males) >= 4) {
            $genderGroups[] = $males;
        }

        // Females with unknown gender
        $femalesWithUnknown = array_merge($females, $unknownGender);
        if (count($femalesWithUnknown) >= 4) {
            $genderGroups[] = $femalesWithUnknown;
        } elseif (count($females) >= 4) {
            $genderGroups[] = $females;
        }

        // If no same-gender groups with >= 4, try all players together
        // (useful when all have unknown gender or mixed genders)
        if (empty($genderGroups) && count($pool) >= 4) {
            $genderGroups[] = $pool;
        }

        // Process each gender group — always generate all tier levels so compareCandidates
        // can pick the best one by fairness/starvation (not just the highest-tier one).
        foreach ($genderGroups as $genderPool) {
            $sameTier = $this->generateSameTierCombinations($genderPool, $request, $userDataMap);
            $candidates = array_merge($candidates, $sameTier);

            $adjacentTier = $this->generateAdjacentTierCombinations($genderPool, $request, $userDataMap);
            $candidates = array_merge($candidates, $adjacentTier);

            $anyTier = $this->generateAnyTierCombinations($genderPool, $request, $userDataMap);
            $candidates = array_merge($candidates, $anyTier);
        }

        return $candidates;
    }

    /**
     * Generate mixed-gender candidate combinations.
     * Requires exactly 2M + 2F (or 2 from each known gender).
     *
     * Handles null gender by treating all players as one group if no valid mixed-gender.
     */
    private function generateMixedGenderCandidates(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): array {
        $candidates = [];

        // Separate by gender - handle null gender
        $males = [];
        $females = [];
        $unknownGender = [];

        foreach ($pool as $p) {
            if ($p->gender === User::MALE) {
                $males[] = $p;
            } elseif ($p->gender === User::FEMALE) {
                $females[] = $p;
            } else {
                $unknownGender[] = $p;
            }
        }

        // Need at least 2 of each known gender for mixed gender
        $hasMixed = count($males) >= 2 && count($females) >= 2;

        if ($hasMixed) {
            // Generate combinations: pick 2 males and 2 females, then pair teams
            $maleCombos = $this->generateCombinations($males, 2);
            $femaleCombos = $this->generateCombinations($females, 2);

            foreach ($maleCombos as $malePair) {
                foreach ($femaleCombos as $femalePair) {
                    $players = array_merge($malePair, $femalePair);

                    // Validate mixed-gender requirements
                    if (!$this->isValidMixedGenderCandidate($players)) {
                        continue;
                    }

                    // Validate tier gap
                    if (!$this->isValidTierGap($players)) {
                        continue;
                    }

                    // Find best team pairing
                    $pairing = $this->findOptimalPairing($players, $userDataMap, $request->settings);
                    if (!$pairing) {
                        continue;
                    }

                    // Build candidate with metadata
                    $tierGap = $this->calculateMaxTierGap($players);
                    $tierMode = $this->hasSameTierCombination($players) ? 'same_tier' :
                                 ($this->hasAdjacentTierCombination($players) ? 'adjacent_tier' : 'mixed_tier');
                    $candidate = $this->buildCandidateMetadata(
                        $players,
                        $pairing['team_a'],
                        $pairing['team_b'],
                        $request,
                        $userDataMap,
                        $tierMode,
                        $tierGap,
                    );
                    $candidates[] = $candidate;
                }
            }
        }

        // Sort by business priority
        usort($candidates, fn($a, $b) => $this->compareCandidates($a, $b));

        return $candidates;
    }

    /**
     * Generate all C(n, k) combinations from array.
     */
    private function generateCombinations(array $array, int $k): array
    {
        $results = [];
        $n = count($array);

        if ($k <= 0 || $k > $n) {
            return $results;
        }

        // For small arrays, generate all combinations
        if ($n <= 10) {
            $this->combinationsRecursive($array, $k, 0, [], $results);
            return $results;
        }

        // For larger arrays, limit by tier grouping first
        // Group by tier to reduce combinations
        $byTier = [];
        foreach ($array as $item) {
            $key = strtolower($item->tier->name);
            $byTier[$key][] = $item;
        }

        // For each tier with enough players, generate combinations within that tier
        foreach ($byTier as $tierKey => $tierItems) {
            if (count($tierItems) >= $k) {
                $this->combinationsRecursive($tierItems, $k, 0, [], $results);
            }
        }

        // Also try combinations across adjacent tiers
        $tierKeys = array_keys($byTier);
        foreach ($tierKeys as $i => $key1) {
            foreach (array_slice($tierKeys, $i + 1) as $key2) {
                $combined = array_merge($byTier[$key1], $byTier[$key2]);
                if (count($combined) >= $k) {
                    $this->combinationsRecursive($combined, $k, 0, [], $results);
                }
            }
        }

        return $results;
    }

    private function combinationsRecursive(array $array, int $k, int $start, array $current, array &$results): void
    {
        if (count($current) === $k) {
            $results[] = $current;
            return;
        }

        for ($i = $start; $i < count($array); $i++) {
            $remaining = $k - count($current) - 1;
            if ($remaining > count($array) - $i - 1) {
                return;
            }
            $current[] = $array[$i];
            $this->combinationsRecursive($array, $k, $i + 1, $current, $results);
            array_pop($current);
        }
    }

    /**
     * Generate same-tier 4-player combinations.
     * E.g., 4 red males.
     */
    private function generateSameTierCombinations(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): array {
        $candidates = [];

        // Group by tier
        $byTier = [];
        foreach ($pool as $p) {
            $key = strtolower($p->tier->name);
            $byTier[$key][] = $p;
        }

        // Sort tiers by priority (highest first)
        uksort($byTier, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        foreach ($byTier as $tierKey => $players) {
            if (count($players) < 4) {
                continue;
            }

            // Generate C(n, 4) combinations for this tier
            $combos = $this->generateCombinations($players, 4);

            foreach ($combos as $players) {
                // Find best team pairing
                $pairing = $this->findOptimalPairing($players, $userDataMap, $request->settings);
                if (!$pairing) {
                    continue;
                }

                $candidate = $this->buildCandidateMetadata(
                    $players,
                    $pairing['team_a'],
                    $pairing['team_b'],
                    $request,
                    $userDataMap,
                    'same_tier',
                    0,
                );
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * Generate 4-player combinations from adjacent tiers.
     * E.g., 2 red + 2 yellow.
     */
    private function generateAdjacentTierCombinations(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): array {
        $candidates = [];

        // Group by tier
        $byTier = [];
        foreach ($pool as $p) {
            $key = strtolower($p->tier->name);
            $byTier[$key][] = $p;
        }

        // Sort tiers by priority
        $tierKeys = array_keys($byTier);
        usort($tierKeys, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        // Try adjacent tier pairs
        for ($i = 0; $i < count($tierKeys) - 1; $i++) {
            $tier1 = $tierKeys[$i];
            $tier2 = $tierKeys[$i + 1];

            // Check if tiers are adjacent
            $t1 = PlayerTier::from($tier1);
            $t2 = PlayerTier::from($tier2);
            if (!PlayerTier::isAdjacent($t1, $t2)) {
                continue;
            }

            $players1 = $byTier[$tier1] ?? [];
            $players2 = $byTier[$tier2] ?? [];

            // Need at least 2 from each tier
            if (count($players1) < 2 || count($players2) < 2) {
                continue;
            }

            // Generate 2+2 combinations
            $combos1 = $this->generateCombinations($players1, 2);
            $combos2 = $this->generateCombinations($players2, 2);

            foreach ($combos1 as $c1) {
                foreach ($combos2 as $c2) {
                    $players = array_merge($c1, $c2);

                    // Validate tier gap
                    if (!$this->isValidTierGap($players)) {
                        continue;
                    }

                    $pairing = $this->findOptimalPairing($players, $userDataMap, $request->settings);
                    if (!$pairing) {
                        continue;
                    }

                    $candidate = $this->buildCandidateMetadata(
                        $players,
                        $pairing['team_a'],
                        $pairing['team_b'],
                        $request,
                        $userDataMap,
                        'adjacent_tier',
                        PlayerTier::tierGap($t1, $t2),
                    );
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * Generate any 4-player combinations (last-resort: no gender/tier restrictions).
     *
     * @param bool $skipGenderValidation When true, accepts any gender composition (e.g. 3M+1F).
     *                                   This is the "must form a match" fallback when gender rules block.
     */
    private function generateAnyTierCombinations(
        array $pool,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
        bool $skipGenderValidation = false,
    ): array {
        $candidates = [];

        // Generate C(n, 4) combinations — no tier restrictions
        $combos = $this->generateCombinations($pool, 4);

        foreach ($combos as $players) {
            $pairing = $this->findOptimalPairing($players, $userDataMap, $request->settings, $skipGenderValidation);
            if (!$pairing) {
                continue;
            }

            $candidate = $this->buildCandidateMetadata(
                $players,
                $pairing['team_a'],
                $pairing['team_b'],
                $request,
                $userDataMap,
                'mixed_tier',
                $this->calculateMaxTierGap($players),
            );
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    /**
     * Build candidate metadata structure.
     */
    private function buildCandidateMetadata(
        array $players,
        array $teamA,
        array $teamB,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
        string $tierMode = 'mixed_tier',
        int $tierGap = 0,
    ): array {
        $settings = $request->settings;

        // Determine gender mode
        $genders = array_unique(array_filter(array_column($players, 'gender'), fn($g) => $g !== null));
        $genderMode = count($genders) === 1 ? 'same_gender' : (count($genders) === 2 ? 'mixed_gender' : 'unknown');

        // Calculate fairness metrics
        $playedCounts = array_column($players, 'played_count');
        $waitingRounds = array_column($players, 'waiting_rounds');

        // Starvation: how many rounds behind the most-played player in the pool each player is.
        // Players with higher starvation should be prioritized (mọi người đều được chơi bằng nhau).
        $starvations = array_map(
            fn($played) => $this->currentPoolMaxPlayed - $played,
            $playedCounts,
        );

        $fairnessMetrics = [
            'sum_played' => array_sum($playedCounts),
            'max_played' => max($playedCounts),
            'min_played' => min($playedCounts),
            'played_range' => max($playedCounts) - min($playedCounts),
            'max_starvation' => max($starvations),
            'min_starvation' => min($starvations),
            'sum_starvation' => array_sum($starvations),
            'max_waiting' => max($waitingRounds),
            'min_waiting' => min($waitingRounds),
            'sum_waiting' => array_sum($waitingRounds),
        ];

        // Calculate rating gap
        $ratingGap = $this->calculateBalanceDiff($teamA, $teamB, $userDataMap);

        // Calculate partner penalty
        $partnerPenalty = $this->calculatePartnerPenalty($players);

        // Check if backup is used
        $usedBackup = false;
        foreach ($players as $p) {
            if ($p->is_backup) {
                $usedBackup = true;
                break;
            }
        }

        // Build signature
        $signature = $this->buildCandidateSignature($teamA, $teamB);

        return [
            'players' => $players,
            'player_ids' => array_values(array_unique(array_map(
                fn($p) => $p->user_id ?? ('m_' . $p->mini_participant_id),
                $players,
            ))),
            'team_a' => $teamA,
            'team_b' => $teamB,
            'gender_mode' => $genderMode,
            'tier_mode' => $tierMode,
            'tier_gap' => $tierGap,
            'rating_gap' => $ratingGap,
            'fairness_metrics' => $fairnessMetrics,
            'partner_penalty' => $partnerPenalty,
            'signature' => $signature,
            'used_backup' => $usedBackup,
            'rules_applied' => [],
        ];
    }

    /**
     * Compare two candidates by business priority.
     * Returns: -1 if a is better, 1 if b is better, 0 if equal
     */
    private function compareCandidates(array $a, array $b): int
    {
        // PRIORITY 0: FAIRNESS — starvation (pool_max_played - player_played) is absolute
        // Người chơi "đói" nhất (ít trận nhất so với pool) phải được ghép TRƯỚC.
        // "Mọi người đều được chơi bằng nhau" = pick the candidate that helps the most-starved player.
        $fmA = $a['fairness_metrics'];
        $fmB = $b['fairness_metrics'];
        $maxStarvA = $fmA['max_starvation'] ?? 0;
        $maxStarvB = $fmB['max_starvation'] ?? 0;

        if ($maxStarvA !== $maxStarvB) {
            // Candidate có người "đói" nhất → thắng
            return $maxStarvB <=> $maxStarvA;
        }

        // Tiebreaker 0a: sum of starvations — candidate that helps more players overall
        $sumStarvA = $fmA['sum_starvation'] ?? 0;
        $sumStarvB = $fmB['sum_starvation'] ?? 0;
        if ($sumStarvA !== $sumStarvB) {
            return $sumStarvB <=> $sumStarvA;
        }

        // Tiebreaker 0b: max_waiting (người chờ lâu nhất)
        $maxWaitA = max($fmA['max_waiting'] ?? 0, $fmA['min_waiting'] ?? 0);
        $maxWaitB = max($fmB['max_waiting'] ?? 0, $fmB['min_waiting'] ?? 0);
        if ($maxWaitA !== $maxWaitB) {
            return $maxWaitB <=> $maxWaitA;
        }

        // Tiebreaker 0c: Anchor inclusion — candidate containing the anchor player must win
        if ($this->currentAnchorId !== null) {
            $aHasAnchor = in_array($this->currentAnchorId, $a['player_ids'] ?? []);
            $bHasAnchor = in_array($this->currentAnchorId, $b['player_ids'] ?? []);
            if ($aHasAnchor !== $bHasAnchor) {
                // 1 = a wins (has anchor), -1 = b wins (has anchor)
                return $aHasAnchor ? 1 : -1;
            }
        }

        // PRIORITY 1: Gender Mode
        // same_gender > mixed_gender > unknown
        // Lower value = better for sorting (so same_gender comes first)
        $genderPriority = ['unknown' => 2, 'mixed_gender' => 1, 'same_gender' => 0];
        $genderCompare = ($genderPriority[$a['gender_mode']] ?? 2) <=> ($genderPriority[$b['gender_mode']] ?? 2);
        if ($genderCompare !== 0) {
            return $genderCompare;
        }

        // PRIORITY 2: Tier Mode
        // same_tier > adjacent_tier > mixed_tier
        $tierModePriority = ['mixed_tier' => 2, 'adjacent_tier' => 1, 'same_tier' => 0];
        $tierModeCompare = ($tierModePriority[$a['tier_mode']] ?? 2) <=> ($tierModePriority[$b['tier_mode']] ?? 2);
        if ($tierModeCompare !== 0) {
            return $tierModeCompare;
        }

        // PRIORITY 3: Tier Gap (smaller is better)
        if ($a['tier_gap'] !== $b['tier_gap']) {
            return $a['tier_gap'] <=> $b['tier_gap'];
        }

        // PRIORITY 4: Team Balance (smaller rating gap is better)
        if (abs($a['rating_gap']) !== abs($b['rating_gap'])) {
            return abs($a['rating_gap']) <=> abs($b['rating_gap']);
        }

        // PRIORITY 5: Fair Play (other metrics)
        // 5a: Sum played (lower is better)
        if ($fmA['sum_played'] !== $fmB['sum_played']) {
            return $fmA['sum_played'] <=> $fmB['sum_played'];
        }

        // 5b: Played range (smaller is better)
        if ($fmA['played_range'] !== $fmB['played_range']) {
            return $fmA['played_range'] <=> $fmB['played_range'];
        }

        // 5c: Min waiting rounds (higher is better - tiebreaker)
        if ($fmA['min_waiting'] !== $fmB['min_waiting']) {
            return $fmB['min_waiting'] <=> $fmA['min_waiting'];
        }

        // PRIORITY 6: Partner History (lower penalty is better)
        if ($a['partner_penalty'] !== $b['partner_penalty']) {
            return $a['partner_penalty'] <=> $b['partner_penalty'];
        }

        // PRIORITY 7: Deterministic tie-break by signature
        return $a['signature'] <=> $b['signature'];
    }

    /**
     * Validate mixed-gender candidate (must be 2M + 2F with symmetric pairing).
     */
    private function isValidMixedGenderCandidate(array $players): bool
    {
        $maleCount = 0;
        $femaleCount = 0;

        foreach ($players as $p) {
            if ($p->gender === User::MALE) {
                $maleCount++;
            } elseif ($p->gender === User::FEMALE) {
                $femaleCount++;
            }
        }

        // Must be exactly 2M + 2F
        return $maleCount === 2 && $femaleCount === 2;
    }

    /**
     * Validate tier gap constraint.
     * All players must be within MAX_TIER_GAP of each other.
     */
    private function isValidTierGap(array $players): bool
    {
        if (count($players) < 2) {
            return true;
        }

        $tiers = array_column($players, 'tier');
        $minPriority = PHP_INT_MAX;
        $maxPriority = PHP_INT_MIN;

        foreach ($tiers as $tier) {
            $priority = $tier->priority();
            $minPriority = min($minPriority, $priority);
            $maxPriority = max($maxPriority, $priority);
        }

        $gap = $maxPriority - $minPriority;

        // Standard limit. If exceeded, still allow when pool is small (<= 4 players)
        // or when all players are guests (last-resort case at the end of a tournament).
        if ($gap <= PlayerTier::MAX_TIER_GAP) {
            return true;
        }

        // Relax rule: end-of-tournament fallback when only 4 players remain.
        // With 4 players, if they don't play now they never will - so allow the match
        // even with larger tier gaps as a last resort.
        if (count($players) === 4) {
            return true;
        }

        return false;
    }

    /**
     * Calculate maximum tier gap in a group of players.
     */
    private function calculateMaxTierGap(array $players): int
    {
        if (count($players) < 2) {
            return 0;
        }

        $tiers = array_column($players, 'tier');
        $priorities = array_map(fn($t) => $t->priority(), $tiers);

        return max($priorities) - min($priorities);
    }

    /**
     * Check if players form a same-tier combination.
     */
    private function hasSameTierCombination(array $players): bool
    {
        $tiers = array_map(fn($p) => $p->tier->value, $players);
        $uniqueTiers = array_unique($tiers);
        return count($uniqueTiers) === 1;
    }

    /**
     * Check if players form an adjacent-tier combination.
     */
    private function hasAdjacentTierCombination(array $players): bool
    {
        $tiers = array_map(fn($p) => $p->tier->value, $players);
        $uniqueTierValues = array_unique($tiers);

        if (count($uniqueTierValues) !== 2) {
            return false;
        }

        $tierValues = array_values($uniqueTierValues);
        $tier1 = PlayerTier::from($tierValues[0]);
        $tier2 = PlayerTier::from($tierValues[1]);
        return PlayerTier::isAdjacent($tier1, $tier2);
    }

    /**
     * Calculate partner repeat penalty for a candidate.
     */
    private function calculatePartnerPenalty(array $players): int
    {
        $penalty = 0;
        $userIds = array_column($players, 'user_id');

        foreach ($players as $p) {
            if (empty($p->partner_ids)) {
                continue;
            }

            foreach ($p->partner_ids as $partnerId) {
                if (in_array($partnerId, $userIds)) {
                    $penalty++;
                }
            }
        }

        // Each partnership counted twice (once per player), so divide by 2
        return (int) floor($penalty / 2);
    }

    /**
     * Public wrapper around buildMatchDTO for callers that already have
     * candidate arrays (e.g. MatchSuggestionService rotation flow).
     */
    public function buildMatchDTOForCandidate(array $team1Players, array $team2Players, array $userDataMap, bool $isHighTier): SuggestionMatchDTO
    {
        return $this->buildMatchDTO($team1Players, $team2Players, $userDataMap, $isHighTier);
    }

    /**
     * Public wrapper around buildWaitingList.
     */
    public function buildWaitingListPublic(array $pool, array $excludeUserIds, array $excludeMiniParticipantIds): array
    {
        return $this->buildWaitingList($pool, $excludeUserIds, $excludeMiniParticipantIds);
    }

    /**
     * Public wrapper around getBackupIfNeeded.
     */
    public function getBackupIfNeededPublic(array $selected, bool $organizerAsBackup): ?PlayerContextDTO
    {
        return $this->getBackupIfNeeded($selected, $organizerAsBackup);
    }

    /**
     * Public wrapper around calculateStatistics.
     */
    public function calculateStatisticsPublic(?SuggestionMatchDTO $match, array $pool, array $selectedIds, array $selectedMiniParticipantIds): array
    {
        return $this->calculateStatistics($match, $pool, $selectedIds, $selectedMiniParticipantIds);
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
        // Handle null user_id (guests) - they use mini_participant_id for exclusion
        if ($request->exclude_player_ids) {
            $excludeIds = $request->exclude_player_ids;
            $pool = array_filter($pool, function ($p) use ($excludeIds) {
                // Guest has null user_id, cannot be excluded by user_id
                if ($p->user_id === null) {
                    return true; // Never exclude guests by user_id
                }
                return !in_array($p->user_id, $excludeIds);
            });
            $pool = array_values($pool);
        }

        // Exclude organizers/staff from pool when organizer_as_backup is disabled
        if (!$request->settings->organizer_as_backup) {
            $pool = array_values(array_filter($pool, fn($p) => !$p->is_backup));
        }

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
     * PRIORITY ORDER (strict, first valid group wins):
     * 1. Same-tier within same gender (e.g., 4 red males)
     * 2. All-male (mixed-tier fallback within same gender)
     * 3. All-female (mixed-tier fallback within same gender)
     * 4. Same-tier mixed gender (e.g., 2 red M + 2 red F) - same tier across genders
     * 5. Unknown gender groups
     * 6. Mixed gender (Nam-Nữ vs Nam-Nữ symmetric)
     * 7. Last resort: use all available players (backup BTC case)
     */
    private function findGenderCompatibleGroups(array $pool): array
    {
        // Separate players by gender
        $males = array_values(array_filter($pool, fn($p) => $p->gender === User::MALE));
        $females = array_values(array_filter($pool, fn($p) => $p->gender === User::FEMALE));
        $unknown = array_values(array_filter($pool, fn($p) => $p->gender === null || $p->gender === User::OTHER || $p->gender === User::NO_PUBLIC));

        $groups = [];

        // PRIORITY 1A: Same-tier groups within each single gender
        $this->addSameTierGroupsForGender($groups, $males);
        $this->addSameTierGroupsForGender($groups, $females);

        // PRIORITY 2: All-male groups (mixed-tier fallback within same gender)
        if (count($males) >= 4) {
            $sortedMales = $this->sortByTierForBalance($males);
            $groups[] = array_slice($sortedMales, 0, max(4, count($sortedMales)));
        }

        // PRIORITY 3: All-female groups
        if (count($females) >= 4) {
            $sortedFemales = $this->sortByTierForBalance($females);
            $groups[] = array_slice($sortedFemales, 0, max(4, count($sortedFemales)));
        }

        // PRIORITY 4: Same-tier mixed gender groups (e.g., 2 red M + 2 red F)
        $this->addSameTierMixedGenderGroups($groups, $males, $females);

        // PRIORITY 5: Unknown gender groups
        if (count($unknown) >= 4) {
            $this->addSameTierGroupsForGender($groups, $unknown);
            $sortedUnknown = $this->sortByTierForBalance($unknown);
            $groups[] = $sortedUnknown;
        }

        // PRIORITY 6: Mixed gender (Nam-Nữ vs Nam-Nữ symmetric) - last resort before backup
        if (empty($groups) && count($males) >= 2 && count($females) >= 2) {
            $sortedMales = $this->sortByTierForBalance($males);
            $sortedFemales = $this->sortByTierForBalance($females);
            $groups[] = array_merge(
                array_slice($sortedMales, 0, 4),
                array_slice($sortedFemales, 0, 4)
            );
        }

        // PRIORITY 7 (Backup): Last resort - use all available players
        if (empty($groups) && count($pool) >= 4) {
            $groups[] = array_values($pool);
        }

        return $groups;
    }

    /**
     * Add same-tier groups for a single gender.
     * E.g., if there are 4+ red males, add them as a group.
     */
    private function addSameTierGroupsForGender(array &$groups, array $players): void
    {
        if (count($players) < 4) return;

        // Group by tier
        $byTier = [];
        foreach ($players as $p) {
            $tierKey = strtolower($p->tier->name);
            $byTier[$tierKey][] = $p;
        }

        // Sort tiers by priority (highest first)
        $tiers = array_keys($byTier);
        usort($tiers, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        // Push groups for tiers with >=4 players
        foreach ($tiers as $tier) {
            if (count($byTier[$tier]) >= 4) {
                $groups[] = array_values($byTier[$tier]);
            }
        }
    }

    /**
     * Add same-tier MIXED GENDER groups.
     * E.g., if there are 2 red males + 2 red females, add them as a mixed same-tier group.
     * Only triggers if neither gender alone has 4 of the same tier.
     */
    private function addSameTierMixedGenderGroups(array &$groups, array $males, array $females): void
    {
        // Group males by tier
        $malesByTier = [];
        foreach ($males as $p) {
            $tierKey = strtolower($p->tier->name);
            $malesByTier[$tierKey][] = $p;
        }

        // Group females by tier
        $femalesByTier = [];
        foreach ($females as $p) {
            $tierKey = strtolower($p->tier->name);
            $femalesByTier[$tierKey][] = $p;
        }

        $allTiers = array_unique(array_merge(array_keys($malesByTier), array_keys($femalesByTier)));
        usort($allTiers, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        foreach ($allTiers as $tier) {
            $malesInTier = $malesByTier[$tier] ?? [];
            $femalesInTier = $femalesByTier[$tier] ?? [];

            $total = count($malesInTier) + count($femalesInTier);
            if ($total >= 4) {
                // Need symmetric pairing: same number per team
                // Take 2M+2F if possible, or other split
                $groups[] = array_merge(array_values($malesInTier), array_values($femalesInTier));
            }
        }
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

        // Same gender or unknown
        // Priority: prefer 4 players of SAME TIER if available
        // Then fallback to fair play priority
        $selected = $this->selectSameTierFirst($players, $request);

        if (count($selected) >= 4) {
            return array_slice($selected, 0, 4);
        }

        // Fallback to fair play priority
        $fresh = array_filter($players, fn($p) => $p->played_count === 0);
        $justPlayed = array_filter($players, fn($p) => $p->played_count > 0);

        if ($request->settings->fair_play) {
            $fresh = $this->applyFairPlayPriority($fresh);
            $justPlayed = $this->applyRestPriority($justPlayed);
        }

        $combined = array_merge($fresh, $justPlayed);

        // Filter out already-selected (same-tier) to avoid duplicates
        $selectedIds = array_column($selected, 'user_id');
        foreach ($combined as $p) {
            if (!in_array($p->user_id, $selectedIds, true)) {
                $selected[] = $p;
                if (count($selected) >= 4) break;
            }
        }

        return array_slice($selected, 0, 4);
    }

    /**
     * Try to select 4 players of the same tier.
     * Order tier priority: highest first (purple > red > yellow > green).
     * Within tier: fair play priority.
     * Returns at least 4 players if any tier has >=4 players.
     */
    private function selectSameTierFirst(array $players, MatchSuggestionRequestDTO $request): array
    {
        // Group by tier
        $byTier = [];
        foreach ($players as $p) {
            $tierKey = strtolower($p->tier->name);
            $byTier[$tierKey][] = $p;
        }

        // Sort tiers by priority (highest first)
        $tiers = array_keys($byTier);
        usort($tiers, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        // Find first tier with >=4 players
        foreach ($tiers as $tier) {
            if (count($byTier[$tier]) >= 4) {
                $tierPlayers = $byTier[$tier];
                if ($request->settings->fair_play) {
                    $tierPlayers = $this->applyFairPlayPriority($tierPlayers);
                }
                return array_slice($tierPlayers, 0, 4);
            }
        }

        return [];
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
    private function findOptimalPairing(array $players, array $userDataMap, $settings, bool $skipGenderValidation = false): ?array
    {
        if (count($players) < 4) {
            return null;
        }

        // Get gender info for validation
        $genderCounts = $this->countGenders($players);
        
        // Try all permutations
        // Use mini_participant_id so guest players (null user_id) are uniquely
        // identifiable in the permutation space.
        $ids = array_map(
            fn($p) => $p->user_id ?? ('m_' . $p->mini_participant_id),
            $players
        );
        $permutations = $this->getPermutations($ids);
        
        $bestPairing = null;
        $bestTierScore = -1.0;      // Tier score cao nhất tìm được
        $bestBalanceDiff = PHP_FLOAT_MAX;
        $preferHighTier = $settings->prefer_high_tier_match ?? false;

        foreach ($permutations as $perm) {
            $teamAIds = array_slice($perm, 0, 2);
            $teamBIds = array_slice($perm, 2, 2);

            $playersA = $this->getPlayersByIds($teamAIds, $players);
            $playersB = $this->getPlayersByIds($teamBIds, $players);

            // Validate gender compatibility (skip in last-resort mode)
            if (!$skipGenderValidation && !$this->isValidGenderPairing($playersA, $playersB, $genderCounts)) {
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
     *
     * Rules:
     * - All-male vs all-male: VALID (Nam-Nam vs Nam-Nam)
     * - All-female vs all-female: VALID (Nữ-Nữ vs Nữ-Nữ)
     * - Mixed: MUST be symmetric - 1M+1F vs 1M+1F (Nam-Nữ vs Nam-Nữ)
     * - Asymmetric (all-male vs all-female, or 2M+0F vs 0M+2F): INVALID
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

        $malesInA = count(array_filter($teamA, fn($p) => $p->gender === User::MALE));
        $malesInB = count(array_filter($teamB, fn($p) => $p->gender === User::MALE));
        $femalesInA = count(array_filter($teamA, fn($p) => $p->gender === User::FEMALE));
        $femalesInB = count(array_filter($teamB, fn($p) => $p->gender === User::FEMALE));

        // If we have mixed gender (both male and female in match), require symmetric distribution.
        if ($hasMale && $hasFemale) {
            return $malesInA === $femalesInA && $malesInB === $femalesInB;
        }

        // When only males (with possibly unknown-gender players), require symmetric male counts.
        // Exception: if one team has no males, the other team must have at most count(teamA)
        // males - covers the end-of-tournament case where a single male must be placed.
        if ($hasMale && !$hasFemale) {
            // Either symmetric, or one-sided is acceptable when small.
            return $malesInA === $malesInB || ($malesInA + $malesInB) <= min(count($teamA), count($teamB));
        }

        if ($hasFemale && !$hasMale) {
            return $femalesInA === $femalesInB || ($femalesInA + $femalesInB) <= min(count($teamA), count($teamB));
        }

        // Unknown gender only - allow
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
        // Map by either user_id (for non-guests) or mini_participant_id (for guests
        // whose user_id is null).
        // - Keys prefix 'u_' for real user ids
        // - Keys prefix 'g_' for mini_participant_id fallbacks (guests)
        $playerMap = [];
        foreach ($players as $p) {
            if ($p->user_id !== null) {
                $playerMap['u_' . $p->user_id] = $p;
            }
            $playerMap['g_' . $p->mini_participant_id] = $p;
        }

        $result = [];
        $seen = [];
        foreach ($userIds as $id) {
            $p = null;
            if ($id !== null && isset($playerMap['u_' . $id])) {
                $p = $playerMap['u_' . $id];
            } elseif ($id !== null && is_string($id) && str_starts_with($id, 'm_')) {
                // Handle "m_{mini_pid}" strings produced by permutations when guest
                // has no real user_id.
                $miniPid = (int) substr($id, 2);
                if (isset($playerMap['g_' . $miniPid])) {
                    $p = $playerMap['g_' . $miniPid];
                }
            } elseif (is_int($id) && isset($playerMap['g_' . $id])) {
                $p = $playerMap['g_' . $id];
            }

            if ($p !== null && !isset($seen[$p->mini_participant_id])) {
                $result[] = $p;
                $seen[$p->mini_participant_id] = true;
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
            mini_participant_id: $player->mini_participant_id,
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
    private function buildWaitingList(array $pool, array $excludeUserIds, array $excludeMiniParticipantIds): array
    {
        return array_values(array_filter($pool, function ($p) use ($excludeUserIds, $excludeMiniParticipantIds) {
            // Guest has null user_id, use mini_participant_id for exclusion check
            if ($p->user_id === null) {
                return !in_array($p->mini_participant_id, $excludeMiniParticipantIds);
            }
            return !in_array($p->user_id, $excludeUserIds);
        }));
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
    private function calculateStatistics(?SuggestionMatchDTO $match, array $pool, array $selectedIds, array $selectedMiniParticipantIds): array
    {
        $selected = array_filter($pool, function ($p) use ($selectedIds, $selectedMiniParticipantIds) {
            if ($p->user_id === null) {
                return in_array($p->mini_participant_id, $selectedMiniParticipantIds);
            }
            return in_array($p->user_id, $selectedIds);
        });
        $waiting = array_filter($pool, function ($p) use ($selectedIds, $selectedMiniParticipantIds) {
            if ($p->user_id === null) {
                return !in_array($p->mini_participant_id, $selectedMiniParticipantIds);
            }
            return !in_array($p->user_id, $selectedIds);
        });

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
        usort($maleTiers, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());
        usort($femaleTiers, fn($a, $b) => PlayerTier::from($b)->priority() - PlayerTier::from($a)->priority());

        // Create low-tier versions (reversed)
        $maleTiersLow = array_reverse($maleTiers);
        $femaleTiersLow = array_reverse($femaleTiers);

        $selected = [];
        $maleHighIdx = 0;
        $maleLowIdx = 0;
        $femaleHighIdx = 0;
        $femaleLowIdx = 0;

        // Pick $count pairs, alternating high/low tier for balance
        for ($i = 0; $i < $count; $i++) {
            // Even iteration: male gets high tier, female gets low tier
            // Odd iteration: male gets low tier, female gets high tier
            if ($i % 2 === 0) {
                // Male: pick from high tier (index 0 = highest priority)
                while ($maleHighIdx < count($maleTiers) && empty($malesByTier[$maleTiers[$maleHighIdx]])) {
                    $maleHighIdx++;
                }
                if ($maleHighIdx < count($maleTiers)) {
                    $tierName = $maleTiers[$maleHighIdx];
                    $selected[] = array_shift($malesByTier[$tierName]);
                }
                // Female: pick from low tier (index 0 = lowest priority in reversed array)
                while ($femaleLowIdx < count($femaleTiersLow) && empty($femalesByTier[$femaleTiersLow[$femaleLowIdx]])) {
                    $femaleLowIdx++;
                }
                if ($femaleLowIdx < count($femaleTiersLow)) {
                    $tierName = $femaleTiersLow[$femaleLowIdx];
                    $selected[] = array_shift($femalesByTier[$tierName]);
                }
            } else {
                // Female: pick from high tier
                while ($femaleHighIdx < count($femaleTiers) && empty($femalesByTier[$femaleTiers[$femaleHighIdx]])) {
                    $femaleHighIdx++;
                }
                if ($femaleHighIdx < count($femaleTiers)) {
                    $tierName = $femaleTiers[$femaleHighIdx];
                    $selected[] = array_shift($femalesByTier[$tierName]);
                }
                // Male: pick from low tier
                while ($maleLowIdx < count($maleTiersLow) && empty($malesByTier[$maleTiersLow[$maleLowIdx]])) {
                    $maleLowIdx++;
                }
                if ($maleLowIdx < count($maleTiersLow)) {
                    $tierName = $maleTiersLow[$maleLowIdx];
                    $selected[] = array_shift($malesByTier[$tierName]);
                }
            }
        }

        return $selected;
    }

    // =========================================================================
    // v2/v3: Helpers for expanding window
    // =========================================================================

    /**
     * Resolve the current round number from the player pool.
     */
    private function resolveCurrentRound(array $pool): int
    {
        $currentRound = 1;
        foreach ($pool as $p) {
            if ($p->last_played_round !== null) {
                $currentRound = max($currentRound, $p->last_played_round + 1);
            }
        }
        return $currentRound;
    }

    /**
     * Split pool into main players and backup (is_backup=true) players.
     *
     * @return array{0: PlayerContextDTO[], 1: PlayerContextDTO[]}
     */
    private function splitMainAndBackupPool(array $pool): array
    {
        $main = [];
        $backup = [];
        foreach ($pool as $p) {
            if ($p->is_backup) {
                $backup[] = $p;
            } else {
                $main[] = $p;
            }
        }
        return [$main, $backup];
    }

    // =========================================================================
    // v2/v3: Expanding Priority Window — Fair Match Suggester
    // Priority: played_count > last_played_round > waiting_rounds > tier
    // Every match found in a window MUST contain the anchor (queue[0]).
    // Only skip anchor recursively if they truly cannot be paired with anyone.
    // =========================================================================

    /**
     * Sort pool by fairness priority.
     * Priority: played_count (asc) > not played prev round > rested longer
     * When fair_play is disabled, shuffle with seed for determinism.
     *
     * @param array $players PlayerContextDTO[]
     * @param int $currentRound Round being scheduled
     * @param bool $useSeed shuffle deterministically when fair_play is off
     * @return array PlayerContextDTO[]
     */
    /**
     * Sort pool by fairness priority.
     * Priority: played_count (asc) > not played prev round > rested longer
     * When $useSeed is provided (int), shuffles deterministically (for fair_play=off).
     * When $useSeed is false, sorts by fairness only (no shuffle).
     *
     * @param array $players PlayerContextDTO[]
     * @param int $currentRound Round being scheduled
     * @param int|false $useSeed shuffle deterministically when int; no shuffle when false
     * @return array PlayerContextDTO[]
     */
    private function sortByFairnessPriority(array $players, int $currentRound, int|false $useSeed = false): array
    {
        if ($useSeed !== false && $useSeed !== null) {
            // Deterministic shuffle — used only when fair_play is off
            mt_srand((int) $useSeed);
            $shuffled = $players;
            for ($i = count($shuffled) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                [$shuffled[$i], $shuffled[$j]] = [$shuffled[$j], $shuffled[$i]];
            }
            mt_srand();
            return $shuffled;
        }

        usort($players, function ($a, $b) use ($currentRound) {
            // 1) played_count ascending — fewer matches = higher priority
            if ($a->played_count !== $b->played_count) {
                return $a->played_count <=> $b->played_count;
            }

            // 2) Did NOT play the round immediately before (more rested)
            $aRested = $a->last_played_round !== null && $a->last_played_round < $currentRound - 1;
            $bRested = $b->last_played_round !== null && $b->last_played_round < $currentRound - 1;
            if ($aRested !== $bRested) {
                return $aRested ? -1 : 1;
            }

            // 3) Rested longer (played longer ago)
            $aLast = $a->last_played_round ?? -999;
            $bLast = $b->last_played_round ?? -999;
            return ($currentRound - $bLast) <=> ($currentRound - $aLast);
        });

        return $players;
    }

    /**
     * Expanding window search — v3 anchor constraint.
     * The anchor (queue[0]) MUST be in any match returned.
     * Only recursively skips anchor when they genuinely cannot pair with anyone.
     *
     * @deprecated Use generateCandidates() instead. This method uses anchor
     * constraint which can lead to suboptimal match selection.
     *
     * @param array $queue Fairness-sorted players (PlayerContextDTO[])
     * @param MatchSuggestionRequestDTO $request
     * @param array $userDataMap
     * @param int $backupStartsAt Index in queue where backup players begin
     * @param int $anchorOffset Starting offset for anchor (default 0)
     * @return array|null ['team_a' => PlayerContextDTO[], 'team_b' => PlayerContextDTO[]]
     */
    private function expandingWindowSearch(
        array $queue,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
        int $backupStartsAt = PHP_INT_MAX,
        int $anchorOffset = 0,
    ): ?array {
        if (count($queue) < 4 || $anchorOffset >= count($queue)) {
            return null;
        }

        $anchor = $queue[$anchorOffset];
        $remainingQueue = array_slice($queue, $anchorOffset);
        $n = count($remainingQueue);

        $mixedOptions = []; // mixed-gender matches found at each window
        $sameOption = null; // first valid same-gender match
        $mixedFairness = []; // fairness scores for each mixed option

        for ($windowSize = 4; $windowSize <= $n; $windowSize++) {
            $window = array_slice($remainingQueue, 0, $windowSize);

            // Try same-gender first (per spec priority)
            $sameResult = $this->tryBestSameGenderIncluding($window, $anchor, $request, $userDataMap);
            if ($sameResult !== null && $sameOption === null) {
                $sameOption = $sameResult;
            }

            // Try mixed-gender as fallback (for when same-gender is impossible or blocked)
            $mixedResult = $this->tryMixedGenderBalancedIncluding($window, $anchor, $request, $userDataMap);
            if ($mixedResult !== null) {
                $mixedOptions[] = $mixedResult;
                $allFour = array_merge($mixedResult['team_a'], $mixedResult['team_b']);
                $mixedFairness[] = min(array_column($allFour, 'played_count'));
            }
        }

        // Decision: prefer match that maximizes fairness.
        //
        // Strategy:
        // 1) If same-gender is possible AND fairer than mixed → pick same-gender
        // 2) If same-gender is possible AND equally fair → prefer larger window
        //    (same-gender at window=7 with equal fairness beats mixed at window=5)
        // 3) If same-gender is impossible → pick mixed (only option)
        //
        // Same-gender "fairer" = lower max played_count in the group.
        $result = null;
        $sameAll = $sameOption !== null
            ? array_merge($sameOption['team_a'], $sameOption['team_b'])
            : [];
        $sameWorst = $sameOption !== null
            ? max(array_column($sameAll, 'played_count'))
            : PHP_INT_MAX;

        $mixedWorst = PHP_INT_MAX;
        $mixedIdx = -1;
        foreach ($mixedFairness as $i => $worst) {
            if ($worst < $mixedWorst) {
                $mixedWorst = $worst;
                $mixedIdx = $i;
            }
        }

        $mixedOption = $mixedIdx >= 0 ? $mixedOptions[$mixedIdx] : null;

        if ($sameOption !== null && $mixedOption !== null) {
            if ($sameWorst < $mixedWorst) {
                $result = $sameOption;
            } elseif ($sameWorst > $mixedWorst) {
                $result = $mixedOption;
            } else {
                $result = $sameOption;
            }
        } elseif ($sameOption !== null) {
            $result = $sameOption;
        } elseif ($mixedOption !== null) {
            $result = $mixedOption;
        }

        if ($result === null) {
            return null;
        }

        $usedBackup = false;
        foreach (array_merge($result['team_a'], $result['team_b']) as $p) {
            $idx = $this->indexOf($p, $queue);
            if ($idx >= $backupStartsAt) {
                $usedBackup = true;
            }
        }
        $result['used_backup'] = $usedBackup;

        return $result;
    }

    /**
     * Same-gender match within window, anchor MUST be included — DEPRECATED.
     *
     * @deprecated Use generateSameGenderCandidates() instead.
     *
     * Only picks same-gender if it doesn't violate fairness:
     * - If opposite gender has 2+ players and has players with lower played_count
     *   than the anchor's same-gender group, mixed is fairer.
     */
    private function tryBestSameGenderIncluding(
        array $window,
        PlayerContextDTO $anchor,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): ?array {
        $sameGender = array_values(array_filter(
            $window,
            fn($p) => $p->gender === $anchor->gender,
        ));
        $oppGender = array_values(array_filter(
            $window,
            fn($p) => $p->gender !== $anchor->gender,
        ));

        if (count($sameGender) < 4) {
            return null;
        }

        // Same-gender fairness gate: skip same-gender if mixed would be fairer.
        // Only block when mixed can actually be formed (opp >= 2 players) AND
        // mixed includes more low-played players (maxSamePlayed > minOppPlayed).
        // This is the original spec logic: same-gender blocked only when it would
        // include higher-played players than the minimum mixed group has.
        // Same-gender fairness gate: block same-tier when mixed would be fairer.
        //
        // When same-gender and mixed have EQUAL fairness (maxSamePlayed === minOppPlayed),
        // same-gender wins the tie-break ONLY if it doesn't leave many people behind
        // (sameGenderCount < 4 means mixed was always going to be chosen anyway, so same-gender
        // wins naturally. sameGenderCount === 4 is a tie-break in same-gender's favor.
        // sameGenderCount >= 5 means same-gender would exclude 1+ person → mixed fairer.)
        //
        // When mixed is STRICTLY fairer (maxSamePlayed > minOppPlayed), block same-gender
        // regardless of sameGenderCount.
        if (count($oppGender) >= 2) {
            $anchorTierCount = 0;
            foreach ($sameGender as $p) {
                if (strtolower($p->tier->name) === strtolower($anchor->tier->name)) {
                    $anchorTierCount++;
                }
            }

            $minOppPlayed = min(array_column($oppGender, 'played_count')) ?: 0;
            $maxSamePlayed = max(array_column($sameGender, 'played_count')) ?: 0;

            $mixedIsBetter = $maxSamePlayed > $minOppPlayed;
            $equalFair = $maxSamePlayed === $minOppPlayed;
            $sameGenderCount = count($sameGender);

            // Block same-gender when mixed is strictly fairer, OR when equal fairness
            // but same-gender would exclude 1+ people (sameGenderCount >= 5)
            $sameTierExcludesAnchor = $anchorTierCount === 4;
            $mixedIsEqual = $anchor->played_count === $minOppPlayed;

            if ($mixedIsBetter || ($equalFair && $sameGenderCount >= 5)) {
                return null;
            }
        }

        // Select 4 same-tier players with anchor validation.
        // If anchor's tier doesn't have 4+, fall back to fairness-ordered top 4.
        $result = $this->bestColorSplit($sameGender, $anchor, $request, $userDataMap);

        if ($result !== null) {
            return $result;
        }

        return null;
    }

    /**
     * Mixed-gender balanced match (1M+1F vs 1M+1F), anchor MUST be included — DEPRECATED.
     *
     * @deprecated Use generateMixedGenderCandidates() instead.
     */
    private function tryMixedGenderBalancedIncluding(
        array $window,
        PlayerContextDTO $anchor,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): ?array {
        $sameGender = array_values(array_filter(
            $window,
            fn($p) => $p->gender === $anchor->gender,
        ));
        $oppGender = array_values(array_filter(
            $window,
            fn($p) => $p->gender !== $anchor->gender,
        ));

        if (count($sameGender) < 2 || count($oppGender) < 2) {
            return null;
        }

        // anchor + same-gender partner (next in queue) + 2 opposite-gender
        $partner = $sameGender[0]->user_id === $anchor->user_id
            ? $sameGender[1]
            : $sameGender[0];
        $o1 = $oppGender[0];
        $o2 = $oppGender[1];

        // Two ways to split teams — pick the one with better VN DUPR balance
        $optA = ['team_a' => [$anchor, $o1], 'team_b' => [$partner, $o2]];
        $optB = ['team_a' => [$anchor, $o2], 'team_b' => [$partner, $o1]];

        $gapA = abs($this->sumRating($optA['team_a'], $userDataMap)
                    - $this->sumRating($optA['team_b'], $userDataMap));
        $gapB = abs($this->sumRating($optB['team_a'], $userDataMap)
                    - $this->sumRating($optB['team_b'], $userDataMap));

        return $gapA <= $gapB ? $optA : $optB;
    }

    /**
     * Within same-gender pool, pick 4 players by color/tier — DEPRECATED.
     *
     * @deprecated Use generateSameTierCombinations() or generateAdjacentTierCombinations() instead.
     * This method uses array_slice which can miss optimal combinations.
     *
     * Priority: 4 same tier (anchor's tier first) > top 4 by fairness order.
     * If anchor's tier doesn't have 4+, falls back to fairness-ordered top 4.
     * Delegates team split to findOptimalPairing for VN DUPR balance.
     */
    private function bestColorSplit(
        array $genderPool,
        PlayerContextDTO $anchor,
        MatchSuggestionRequestDTO $request,
        array $userDataMap,
    ): ?array {
        $byTier = [];
        foreach ($genderPool as $p) {
            $key = strtolower($p->tier->name);
            $byTier[$key][] = $p;
        }

        $anchorTierKey = strtolower($anchor->tier->name);

        // 1) Anchor's tier has 4+ → prefer same-tier group (tier gate applied)
        if (isset($byTier[$anchorTierKey]) && count($byTier[$anchorTierKey]) >= 4) {
            $chosen = array_slice($byTier[$anchorTierKey], 0, 4);
            $pairing = $this->findOptimalPairing($chosen, $userDataMap, $request->settings);
            if ($pairing) {
                return $pairing;
            }
        }

        // 2) Not enough same-tier (or optimal pairing failed) — fairness fallback.
        //    genderPool is already sorted by played_count, so top 4 includes anchor.
        if (count($genderPool) >= 4) {
            $chosen = array_slice($genderPool, 0, 4);
            $pairing = $this->findOptimalPairing($chosen, $userDataMap, $request->settings);
            if ($pairing) {
                return $pairing;
            }
        }

        return null;
    }

    private function sumRating(array $players, array $userDataMap): float
    {
        $total = 0.0;
        foreach ($players as $p) {
            if ($p->vndupr_score !== null) {
                $total += $p->vndupr_score;
            } elseif ($p->user_id && isset($userDataMap[$p->user_id])) {
                $vndupr = $userDataMap[$p->user_id]['sports'][0]['scores']['vndupr_score'] ?? null;
                if ($vndupr !== null && is_numeric($vndupr)) {
                    $total += (float) $vndupr;
                } else {
                    $total += $p->tier->score();
                }
            } else {
                $total += $p->tier->score();
            }
        }
        return $total;
    }

    private function indexOf(PlayerContextDTO $player, array $list): int
    {
        foreach ($list as $i => $p) {
            if ($p->user_id === $player->user_id) {
                return $i;
            }
        }
        return PHP_INT_MAX;
    }
}
