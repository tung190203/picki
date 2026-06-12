<?php

namespace App\Services;

use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Services\Tournament\BipartiteRoundRobinService;
use Illuminate\Support\Facades\DB;

class RoundRobinSchedulerService
{
    const MATCH_TYPE_SINGLE = 'single';
    const MATCH_TYPE_DOUBLE = 'double';

    /**
     * Generate partner_rotation schedule (minimum 6 individual players).
     * Each player plays against every other player exactly once.
     * Distributed across rounds using available courts.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generatePartnerRotationSchedule(array $playerIds, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        $n = count($playerIds);
        if ($n < 6) {
            throw new \InvalidArgumentException('partner_rotation requires at least 6 players, got ' . $n);
        }

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            if ($n < 4) {
                throw new \InvalidArgumentException('double partner_rotation requires at least 4 players, got ' . $n);
            }
            return $this->generateDoublePartnerRotation($playerIds);
        }

        $allMatches = $this->generateAllPairs($playerIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $playerIds);

        $matchesPerPlayer = array_fill_keys($playerIds, 0);
        foreach ($allMatches as $match) {
            $matchesPerPlayer[$match['participant1_id']]++;
            $matchesPerPlayer[$match['participant2_id']]++;
        }

        $unbalancedNotice = null;
        if ($n === 6 || $n === 7) {
            $min = min($matchesPerPlayer);
            $max = max($matchesPerPlayer);
            if ($min !== $max) {
                $unbalancedNotice = "Số trận không đều: {$min}-{$max} trận/người.";
            }
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'matches_per_player' => $matchesPerPlayer,
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => [],
        ];
    }

    /**
     * Generate double partner rotation schedule using circle method.
     *
     * Player 0 fixed as anchor. Remaining (n-1) players rotate left by 1 per round.
     * Each round: first half of rotated circle vs second half.
     * All players in team1 play all players in team2.
     *
     * Odd n: 1 player sits out per round (bye). n rounds total.
     * Even n: no bye. n-1 rounds total.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @return array
     */
    private function generateDoublePartnerRotation(array $playerIds): array
    {
        $n = count($playerIds);
        $playerIds = array_values($playerIds);

        // Bước 1: 1-factorization — tạo tất cả C(n,2) pairs
        $allPairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $allPairs[] = [
                    'participant1_id' => $playerIds[$i],
                    'participant2_id' => $playerIds[$j],
                ];
            }
        }

        // Bước 2: Greedy coloring — pack pairs vào rounds (no player conflict)
        $rounds = $this->distributeMatchesIntoRounds($allPairs, $playerIds, true);

        // Bước 3: Nhóm mỗi 2 pairs liền kề thành match pair-vs-pair
        // Odd n: cặp cuối cùng → bye match
        foreach ($rounds as &$round) {
            $newMatches = [];
            $pairs = $round['matches'];
            for ($i = 0; $i < count($pairs); $i += 2) {
                $p1 = $pairs[$i];
                $team1 = array_filter([$p1['participant1_id'], $p1['participant2_id']]);

                if ($i + 1 < count($pairs)) {
                    $p2 = $pairs[$i + 1];
                    $team2 = array_filter([$p2['participant1_id'], $p2['participant2_id']]);
                    $isBye = false;
                    $byePlayer = null;
                } else {
                    // Odd: cặp cuối → bye (1 player ngồi ngoài)
                    $team2 = [];
                    $isBye = true;
                    $byePlayer = array_values($team1)[1] ?? null;
                }

                $newMatches[] = [
                    'team1_players' => array_values($team1),
                    'team2_players' => array_values($team2),
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => $isBye,
                    'bye_player_id' => $byePlayer,
                ];
            }
            $round['matches'] = $newMatches;
        }
        unset($round);

        $matchesPerPlayer = array_fill_keys($playerIds, $n - 1);

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allPairs),
                'matches_per_player' => $matchesPerPlayer,
                'unbalanced_notice' => null,
            ],
            'teams' => [],
        ];
    }

    /**
     * Generate mixed_gender schedule using partnership-based round robin.
     *
     * Each male partners with each female exactly once.
     * Partnerships are distributed into rounds with no player conflicts.
     *
     * Single format: each partnership (male, female) = one match.
     * Double format: two partnerships are paired into one match (mixed team vs mixed team).
     *
     * @param array $maleIds Array of MiniParticipant IDs (male)
     * @param array $femaleIds Array of MiniParticipant IDs (female)
     * @param string $matchType 'single' or 'double'
     * @param bool $shuffle Randomize order of players before scheduling
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateMixedGenderSchedule(
        array $maleIds,
        array $femaleIds,
        string $matchType = self::MATCH_TYPE_SINGLE,
        bool $shuffle = true
    ): array {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female player');
        }

        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        if ($shuffle) {
            shuffle($maleIds);
            shuffle($femaleIds);
        }

        // Step 1: Cartesian product = all male x female partnerships
        $allPartnerships = [];
        foreach ($maleIds as $mid) {
            foreach ($femaleIds as $fid) {
                $allPartnerships[] = ['male_id' => $mid, 'female_id' => $fid];
            }
        }

        // Step 2: Use BipartiteRoundRobinService for round distribution
        $bipartite = BipartiteRoundRobinService::generate($maleIds, $femaleIds, false);
        $bipartiteRounds = $bipartite['rounds'];

        // Step 3: Build matches from partnership rounds
        $oppHistory = []; // $oppHistory[$pKey1][$pKey2] = encounter count
        $rounds = [];
        $allMatches = [];

        foreach ($bipartiteRounds as $roundIdx => $bRound) {
            // Separate real partnerships from BYE entries (is_bye = false → keep, is_bye = true → BYE)
            $byeEntries = array_values(array_filter($bRound, fn($m) => !empty($m['is_bye'])));
            $rawPartnerships = array_values(array_filter($bRound, fn($m) => empty($m['is_bye'])));

            // Normalize Bipartite output (player_a/player_b) to our format (male_id/female_id)
            $roundPartnerships = array_map(fn($p) => [
                'male_id' => $p['player_a'],
                'female_id' => $p['player_b'],
            ], $rawPartnerships);

            if ($matchType === self::MATCH_TYPE_DOUBLE) {
                $roundMatches = $this->buildDoubleMatchesFromPartnerships($roundPartnerships, $oppHistory);
            } else {
                $roundMatches = $this->convertPartnershipsToSingleMatches($roundPartnerships);
            }

            // Only append BYE entries to round display for double format.
            // Single format BYEs are informational (player sits out); they are NOT matches.
            if ($matchType === self::MATCH_TYPE_DOUBLE) {
                foreach ($byeEntries as $bye) {
                    $playerId = $bye['player_a'] ?? $bye['player_b'];
                    $isMale = in_array($playerId, $maleIds, true);
                    $male = $isMale ? $playerId : null;
                    $female = !$isMale ? $playerId : null;
                    $roundMatches[] = [
                        'team1_players' => array_filter([$male, $female]),
                        'team2_players' => [],
                        'team1_id' => null,
                        'team2_id' => null,
                        'is_bye' => true,
                        'bye_side' => 'team2',
                    ];
                }
            }

            $rounds[] = ['round_number' => $roundIdx + 1, 'matches' => $roundMatches];
            foreach ($roundMatches as $m2) {
                $allMatches[] = $m2;
            }
        }

        // Step 4: Calculate per-player match counts
        $maleMatchCount = array_fill_keys($maleIds, 0);
        $femaleMatchCount = array_fill_keys($femaleIds, 0);

        foreach ($allMatches as $match) {
            if (!empty($match['is_bye'])) {
                continue; // BYE = no play
            }
            $this->countMatchPlayers($match, $maleMatchCount, $femaleMatchCount);
        }

        $unbalancedNotice = null;
        if ($m !== $f) {
            $unbalancedNotice = "{$m} nam sẽ đánh {$f} trận, {$f} nữ sẽ đánh {$m} trận.";
        }
        $byeBalanced = $this->isPlayerByeBalanced($maleMatchCount, $femaleMatchCount, $m, $f);
        if (!$byeBalanced) {
            $notice = $this->buildByeUnbalancedNotice($maleMatchCount, $femaleMatchCount);
            $unbalancedNotice = $unbalancedNotice
                ? "{$unbalancedNotice} {$notice}"
                : $notice;
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'male_matches' => $maleMatchCount,
                'female_matches' => $femaleMatchCount,
                'matches_per_male' => $f,
                'matches_per_female' => $m,
                'bye_balanced' => $byeBalanced,
                'bye_count_male' => [],
                'bye_count_female' => [],
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => [],
        ];
    }

    /**
     * Build double-format matches from a list of partnerships in one round.
     * Greedy pairing: prefer opponents with fewest prior encounters (opposition history).
     * Odd partnership count → last one gets a BYE.
     *
     * @param array $partnerships  List of ['male_id' => int, 'female_id' => int]
     * @param array &$oppHistory    [$pKey1][$pKey2] = encounter count, passed by reference
     * @return array List of match arrays
     */
    private function buildDoubleMatchesFromPartnerships(array $partnerships, array &$oppHistory): array
    {
        $matches = [];
        if (empty($partnerships)) {
            return $matches;
        }

        // Assign stable keys to partnerships so we can track them
        $indexed = [];
        foreach ($partnerships as $p) {
            $indexed[] = ['p' => $p, 'paired' => false];
        }

        $n = count($indexed);
        for ($i = 0; $i < $n; $i++) {
            if ($indexed[$i]['paired']) {
                continue;
            }

            $p1 = $indexed[$i]['p'];
            $p1Key = $this->partnershipKey($p1);
            $indexed[$i]['paired'] = true;

            // Find best opponent: unpaired, minimal opposition history
            $bestIdx = null;
            $bestScore = PHP_INT_MAX;

            for ($j = $i + 1; $j < $n; $j++) {
                if ($indexed[$j]['paired']) {
                    continue;
                }
                $p2 = $indexed[$j]['p'];
                $p2Key = $this->partnershipKey($p2);

                $score = ($oppHistory[$p1Key][$p2Key] ?? 0) + ($oppHistory[$p2Key][$p1Key] ?? 0);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $j;
                }
            }

            if ($bestIdx !== null) {
                $p2 = $indexed[$bestIdx]['p'];
                $p2Key = $this->partnershipKey($p2);
                $indexed[$bestIdx]['paired'] = true;

                $oppHistory[$p1Key][$p2Key] = ($oppHistory[$p1Key][$p2Key] ?? 0) + 1;
                $oppHistory[$p2Key][$p1Key] = ($oppHistory[$p2Key][$p1Key] ?? 0) + 1;

                $matches[] = [
                    'team1_players' => [$p1['male_id'], $p1['female_id']],
                    'team2_players' => [$p2['male_id'], $p2['female_id']],
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => false,
                ];
            } else {
                // No opponent found — BYE
                $matches[] = [
                    'team1_players' => [$p1['male_id'], $p1['female_id']],
                    'team2_players' => [],
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => true,
                    'bye_side' => 'team2',
                ];
            }
        }

        return $matches;
    }

    /**
     * Build a stable string key for a partnership.
     */
    private function partnershipKey(array $p): string
    {
        return $p['male_id'] . '-' . $p['female_id'];
    }

    /**
     * Count players from a match into male/female match counters.
     */
    private function countMatchPlayers(array $match, array &$maleCounts, array &$femaleCounts): void
    {
        // Double format: team1_players / team2_players
        if (!empty($match['team1_players']) || !empty($match['team2_players'])) {
            foreach (['team1_players', 'team2_players'] as $key) {
                if (!empty($match[$key]) && is_array($match[$key])) {
                    foreach ($match[$key] as $pid) {
                        if (isset($maleCounts[$pid])) {
                            $maleCounts[$pid]++;
                        } elseif (isset($femaleCounts[$pid])) {
                            $femaleCounts[$pid]++;
                        }
                    }
                }
            }
            return;
        }

        // Single format: participant1_id / participant2_id
        foreach (['participant1_id', 'participant2_id'] as $key) {
            if (!empty($match[$key])) {
                $pid = $match[$key];
                if (isset($maleCounts[$pid])) {
                    $maleCounts[$pid]++;
                } elseif (isset($femaleCounts[$pid])) {
                    $femaleCounts[$pid]++;
                }
            }
        }
    }

    /**
     * Convert partnerships to single-format matches.
     * Each partnership = one match between the male and female.
     */
    private function convertPartnershipsToSingleMatches(array $roundPartnerships): array
    {
        $matches = [];

        foreach ($roundPartnerships as $p) {
            $matches[] = [
                'participant1_id' => $p['male_id'],
                'participant2_id' => $p['female_id'],
                'is_bye' => false,
            ];
        }

        return $matches;
    }

    /**
     * Check if BYE distribution is balanced across male and female players.
     * A player gets a BYE when they have no match in a round.
     */
    private function isPlayerByeBalanced(array $maleMatches, array $femaleMatches, int $numMales, int $numFemales): bool
    {
        $totalMaleMatches = array_sum($maleMatches);
        $totalFemaleMatches = array_sum($femaleMatches);

        if ($numMales === 0 || $numFemales === 0) {
            return true;
        }

        $expectedMaleMatches = $numFemales;
        $expectedFemaleMatches = $numMales;

        $avgMale = $totalMaleMatches / $numMales;
        $avgFemale = $totalFemaleMatches / $numFemales;

        $maxDiff = max(abs($avgMale - $expectedMaleMatches), abs($avgFemale - $expectedFemaleMatches));

        // Allow max 1 difference due to rounding with unequal group sizes
        return $maxDiff < 1.1;
    }

    /**
     * Build a human-readable notice about BYE imbalance.
     */
    private function buildByeUnbalancedNotice(array $maleMatches, array $femaleMatches): string
    {
        if (!empty($maleMatches)) {
            $maleMin = min($maleMatches);
            $maleMax = max($maleMatches);
            if ($maleMin !== $maleMax) {
                $notice = "BYE nam chênh lệch: {$maleMin}-{$maleMax} lần.";
                if (!empty($femaleMatches)) {
                    $femaleMin = min($femaleMatches);
                    $femaleMax = max($femaleMatches);
                    if ($femaleMin !== $femaleMax) {
                        $notice .= " BYE nữ chênh lệch: {$femaleMin}-{$femaleMax} lần.";
                    }
                }
                return $notice;
            }
        }
        if (!empty($femaleMatches)) {
            $femaleMin = min($femaleMatches);
            $femaleMax = max($femaleMatches);
            if ($femaleMin !== $femaleMax) {
                return "BYE nữ chênh lệch: {$femaleMin}-{$femaleMax} lần.";
            }
        }
        return '';
    }

    /**
     * Generate rank_pairing schedule.
     * Each A plays each B exactly once using Bipartite Round Robin.
     *
     * @param array $aIds Array of MiniParticipant IDs (group A)
     * @param array $bIds Array of MiniParticipant IDs (group B)
     * @param string $matchType 'single' or 'double'
     * @param bool $shuffle Randomize order of players before scheduling
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateRankPairingSchedule(
        array $aIds,
        array $bIds,
        string $matchType = self::MATCH_TYPE_SINGLE,
        bool $shuffle = true
    ): array {
        $na = count($aIds);
        $nb = count($bIds);

        if ($na < 1 || $nb < 1) {
            throw new \InvalidArgumentException('rank_pairing requires at least 1 player in each group');
        }

        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        if ($shuffle) {
            shuffle($aIds);
            shuffle($bIds);
        }

        // Step 1: Cartesian product A x B = all partnerships
        $allPartnerships = [];
        foreach ($aIds as $aid) {
            foreach ($bIds as $bid) {
                $allPartnerships[] = ['a_id' => $aid, 'b_id' => $bid];
            }
        }

        // Step 2: Use BipartiteRoundRobinService for round distribution
        $bipartite = BipartiteRoundRobinService::generate($aIds, $bIds, false);
        $bipartiteRounds = $bipartite['rounds'];

        // Step 3: Build matches from partnership rounds
        $oppHistory = [];
        $rounds = [];
        $allMatches = [];

        foreach ($bipartiteRounds as $roundIdx => $bRound) {
            // Separate real partnerships from BYE entries
            $byeEntries = array_values(array_filter($bRound, fn($m) => !empty($m['is_bye'])));
            $rawPartnerships = array_values(array_filter($bRound, fn($m) => empty($m['is_bye'])));

            // Normalize Bipartite output (player_a/player_b) to our format (a_id/b_id)
            $roundPartnerships = array_map(fn($p) => [
                'a_id' => $p['player_a'],
                'b_id' => $p['player_b'],
            ], $rawPartnerships);

            if ($matchType === self::MATCH_TYPE_DOUBLE) {
                $roundMatches = $this->buildRankPairingMatches($roundPartnerships, $oppHistory);
            } else {
                $roundMatches = $this->convertRankPartnershipsToSingleMatches($roundPartnerships);
            }

            // Only append BYE entries to round display for double format.
            // Single format BYEs are informational; they are NOT matches.
            if ($matchType === self::MATCH_TYPE_DOUBLE) {
                foreach ($byeEntries as $bye) {
                    $playerId = $bye['player_a'] ?? $bye['player_b'];
                    $isA = in_array($playerId, $aIds, true);
                    $a = $isA ? $playerId : null;
                    $b = !$isA ? $playerId : null;
                    $roundMatches[] = [
                        'team1_players' => array_filter([$a, $b]),
                        'team2_players' => [],
                        'team1_id' => null,
                        'team2_id' => null,
                        'is_bye' => true,
                        'bye_side' => 'team2',
                    ];
                }
            }

            $rounds[] = ['round_number' => $roundIdx + 1, 'matches' => $roundMatches];
            foreach ($roundMatches as $m2) {
                $allMatches[] = $m2;
            }
        }

        // Stats
        $aMatches = array_fill_keys($aIds, 0);
        $bMatches = array_fill_keys($bIds, 0);
        foreach ($allMatches as $match) {
            if (!empty($match['is_bye'])) {
                continue;
            }
            $this->countRankMatchPlayers($match, $aMatches, $bMatches);
        }

        $unbalancedNotice = null;
        if ($na !== $nb) {
            $unbalancedNotice = "{$na} A sẽ đánh {$nb} trận, {$nb} B sẽ đánh {$na} trận.";
        }
        $byeBalanced = $this->isRankPairingByeBalanced($aMatches, $bMatches, $na, $nb);
        if (!$byeBalanced) {
            $notice = $this->buildRankPairingByeUnbalancedNotice($aMatches, $bMatches);
            $unbalancedNotice = $unbalancedNotice ? "{$unbalancedNotice} {$notice}" : $notice;
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'a_matches' => $aMatches,
                'b_matches' => $bMatches,
                'matches_per_a' => $nb,
                'matches_per_b' => $na,
                'bye_balanced' => $byeBalanced,
                'bye_count_a' => [],
                'bye_count_b' => [],
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => [],
        ];
    }

    /**
     * Build double-format matches from A/B partnerships using greedy opposition-history pairing.
     *
     * @param array $partnerships  List of ['a_id' => int, 'b_id' => int]
     * @param array &$oppHistory   Passed by reference
     * @return array
     */
    private function buildRankPairingMatches(array $partnerships, array &$oppHistory): array
    {
        $matches = [];
        if (empty($partnerships)) {
            return $matches;
        }

        $indexed = [];
        foreach ($partnerships as $p) {
            $indexed[] = ['p' => $p, 'paired' => false];
        }

        $n = count($indexed);
        for ($i = 0; $i < $n; $i++) {
            if ($indexed[$i]['paired']) {
                continue;
            }

            $p1 = $indexed[$i]['p'];
            $p1Key = $p1['a_id'] . '-' . $p1['b_id'];
            $indexed[$i]['paired'] = true;

            $bestIdx = null;
            $bestScore = PHP_INT_MAX;

            for ($j = $i + 1; $j < $n; $j++) {
                if ($indexed[$j]['paired']) {
                    continue;
                }
                $p2 = $indexed[$j]['p'];
                $p2Key = $p2['a_id'] . '-' . $p2['b_id'];

                $score = ($oppHistory[$p1Key][$p2Key] ?? 0) + ($oppHistory[$p2Key][$p1Key] ?? 0);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $j;
                }
            }

            if ($bestIdx !== null) {
                $p2 = $indexed[$bestIdx]['p'];
                $p2Key = $p2['a_id'] . '-' . $p2['b_id'];
                $indexed[$bestIdx]['paired'] = true;

                $oppHistory[$p1Key][$p2Key] = ($oppHistory[$p1Key][$p2Key] ?? 0) + 1;
                $oppHistory[$p2Key][$p1Key] = ($oppHistory[$p2Key][$p1Key] ?? 0) + 1;

                $matches[] = [
                    'team1_players' => [$p1['a_id'], $p1['b_id']],
                    'team2_players' => [$p2['a_id'], $p2['b_id']],
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => false,
                ];
            } else {
                $matches[] = [
                    'team1_players' => [$p1['a_id'], $p1['b_id']],
                    'team2_players' => [],
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => true,
                    'bye_side' => 'team2',
                ];
            }
        }

        return $matches;
    }

    /**
     * Convert A/B partnerships to single-format matches.
     */
    private function convertRankPartnershipsToSingleMatches(array $partnerships): array
    {
        return array_map(fn($p) => [
            'participant1_id' => $p['a_id'],
            'participant2_id' => $p['b_id'],
            'is_bye' => false,
        ], $partnerships);
    }

    /**
     * Count players from a rank_pairing match into A/B counters.
     */
    private function countRankMatchPlayers(array $match, array &$aCounts, array &$bCounts): void
    {
        // Double format
        if (!empty($match['team1_players']) || !empty($match['team2_players'])) {
            foreach (['team1_players', 'team2_players'] as $key) {
                if (!empty($match[$key]) && is_array($match[$key])) {
                    foreach ($match[$key] as $pid) {
                        if (isset($aCounts[$pid])) {
                            $aCounts[$pid]++;
                        } elseif (isset($bCounts[$pid])) {
                            $bCounts[$pid]++;
                        }
                    }
                }
            }
            return;
        }

        // Single format
        foreach (['participant1_id', 'participant2_id'] as $key) {
            if (!empty($match[$key])) {
                $pid = $match[$key];
                if (isset($aCounts[$pid])) {
                    $aCounts[$pid]++;
                } elseif (isset($bCounts[$pid])) {
                    $bCounts[$pid]++;
                }
            }
        }
    }

    /**
     * Check BYE balance for rank_pairing.
     */
    private function isRankPairingByeBalanced(array $aMatches, array $bMatches, int $na, int $nb): bool
    {
        if ($na === 0 || $nb === 0) {
            return true;
        }
        $avgA = array_sum($aMatches) / $na;
        $avgB = array_sum($bMatches) / $nb;
        $maxDiff = max(abs($avgA - $nb), abs($avgB - $na));
        return $maxDiff < 1.1;
    }

    /**
     * Build BYE unbalanced notice for rank_pairing.
     */
    private function buildRankPairingByeUnbalancedNotice(array $aMatches, array $bMatches): string
    {
        $notice = '';
        if (!empty($aMatches)) {
            $minA = min($aMatches);
            $maxA = max($aMatches);
            if ($minA !== $maxA) {
                $notice = "BYE A chênh lệch: {$minA}-{$maxA} lần.";
            }
        }
        if (!empty($bMatches)) {
            $minB = min($bMatches);
            $maxB = max($bMatches);
            if ($minB !== $maxB) {
                $notice .= ($notice ? ' ' : '') . "BYE B chênh lệch: {$minB}-{$maxB} lần.";
            }
        }
        return $notice;
    }

    /**
     * Calculate leaderboard for a mini tournament.
     * For rank_pairing format, returns separate A and B leaderboards.
     *
     * @param int $miniTournamentId
     * @return array{leaderboard: array, group_a_leaderboard: array|null, group_b_leaderboard: array|null}
     */
    public function calculateLeaderboard(int $miniTournamentId): array
    {
        $miniTournament = MiniTournament::find($miniTournamentId);
        if (!$miniTournament) {
            return ['leaderboard' => []];
        }

        $isRankPairing = $miniTournament->match_format === MiniTournament::MATCH_FORMAT_RANK_PAIRING;

        $participants = MiniParticipant::with('user:id,full_name')
            ->where('mini_tournament_id', $miniTournamentId)
            ->get()
            ->keyBy('id');

        // Map user_id -> participant_id for quick lookup
        $userToParticipant = $participants->map(fn($p) => $p->id)->flip()->toArray();

        $matches = MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->whereNotNull('round_number')
            ->where('status', MiniMatch::STATUS_COMPLETED)
            ->with([
                'team1.members.user',
                'team2.members.user',
            ])
            ->get();

        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->id] = [
                'participant_id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->full_name ?? ($p->guest_name ?? 'Khách'),
                'player_group' => $p->player_group,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'total_matches' => 0,
                'total_point_diff' => 0,
            ];
        }

        foreach ($matches as $match) {
            if ($match->is_bye) {
                continue;
            }
            if ($match->team_1_score === null || $match->team_2_score === null) {
                continue;
            }

            $s1 = $match->team_1_score;
            $s2 = $match->team_2_score;

            // Resolve participant IDs from team members (works for both single & double format)
            $p1 = $this->resolveParticipantId($match->team1, $userToParticipant);
            $p2 = $this->resolveParticipantId($match->team2, $userToParticipant);

            if ($p1 === null || $p2 === null) {
                continue;
            }

            if (!isset($stats[$p1]) || !isset($stats[$p2])) {
                continue;
            }

            $stats[$p1]['total_matches']++;
            $stats[$p2]['total_matches']++;
            $stats[$p1]['total_point_diff'] += $s1 - $s2;
            $stats[$p2]['total_point_diff'] += $s2 - $s1;

            if ($s1 > $s2) {
                $stats[$p1]['wins']++;
                $stats[$p2]['losses']++;
            } elseif ($s2 > $s1) {
                $stats[$p2]['wins']++;
                $stats[$p1]['losses']++;
            } else {
                $stats[$p1]['draws']++;
                $stats[$p2]['draws']++;
            }
        }

        $enrichStats = function (array $items): array {
            return collect($items)->map(function ($s) {
                $s['win_rate'] = $s['total_matches'] > 0
                    ? round($s['wins'] / $s['total_matches'] * 100, 1)
                    : 0;
                $s['avg_point_diff'] = $s['total_matches'] > 0
                    ? round($s['total_point_diff'] / $s['total_matches'], 1)
                    : 0;
                return $s;
            })->sort(function ($a, $b) {
                if ($b['wins'] !== $a['wins']) {
                    return $b['wins'] - $a['wins'];
                }
                if (abs($b['avg_point_diff'] - $a['avg_point_diff']) > 0.01) {
                    return $b['avg_point_diff'] <=> $a['avg_point_diff'];
                }
                return $b['total_matches'] - $a['total_matches'];
            })->values()->map(function ($s, $idx) {
                $s['rank'] = $idx + 1;
                return $s;
            })->all();
        };

        $allLeaderboard = $enrichStats($stats);

        if ($isRankPairing) {
            $groupAStats = array_filter($stats, fn($s) => $s['player_group'] === 'a');
            $groupBStats = array_filter($stats, fn($s) => $s['player_group'] === 'b');

            return [
                'leaderboard' => $allLeaderboard,
                'group_a_leaderboard' => $enrichStats($groupAStats),
                'group_b_leaderboard' => $enrichStats($groupBStats),
            ];
        }

        return [
            'leaderboard' => $allLeaderboard,
        ];
    }

    /**
     * Generate all unique pairs for a set of player IDs (Round Robin).
     *
     * @param array $playerIds
     * @return array
     */
    private function generateAllPairs(array $playerIds): array
    {
        $matches = [];
        $n = count($playerIds);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $matches[] = [
                    'participant1_id' => $playerIds[$i],
                    'participant2_id' => $playerIds[$j],
                    'is_bye' => false,
                ];
            }
        }
        return $matches;
    }

    /**
     * Distribute matches into rounds using greedy round-grouping.
     * Each round contains all matches that can be played simultaneously
     * (no player appears twice in the same round).
     * When a player has no match in a round, a bye match is added for them.
     *
     * @param array $allMatches
     * @param array $allPlayerIds All player IDs in this tournament
     * @return array
     */
    private function distributeMatchesIntoRounds(array $allMatches, array $allPlayerIds, bool $skipAutoBye = false): array
    {
        $totalMatches = count($allMatches);
        if ($totalMatches === 0) {
            return [];
        }

        $rounds = [];
        $remaining = $allMatches;
        $roundNumber = 1;

        while (!empty($remaining)) {
            $roundMatches = [];
            $playersInRound = [];

            // Greedy: add all non-conflicting matches to current round
            foreach ($remaining as $idx => $match) {
                $matchPlayers = $this->getMatchPlayerIds($match);

                $hasConflict = false;
                foreach ($matchPlayers as $pid) {
                    if (in_array($pid, $playersInRound, true)) {
                        $hasConflict = true;
                        break;
                    }
                }

                if (!$hasConflict) {
                    $roundMatches[] = $match;
                    foreach ($matchPlayers as $pid) {
                        $playersInRound[] = $pid;
                    }
                    unset($remaining[$idx]);
                }
            }

            // Deadlock: all remaining matches conflict → pick first to break
            if (empty($roundMatches) && !empty($remaining)) {
                $firstKey = array_key_first($remaining);
                $roundMatches[] = $remaining[$firstKey];
                $matchPlayers = $this->getMatchPlayerIds($remaining[$firstKey]);
                foreach ($matchPlayers as $pid) {
                    $playersInRound[] = $pid;
                }
                unset($remaining[$firstKey]);
            }

            // Add bye matches for unassigned players (skip if already handled by scheduler)
            if (!$skipAutoBye) {
                foreach ($allPlayerIds as $pid) {
                    if (!in_array($pid, $playersInRound, true)) {
                        $roundMatches[] = [
                            'participant1_id' => $pid,
                            'participant2_id' => null,
                            'is_bye' => true,
                        ];
                    }
                }
            }

            $rounds[] = [
                'round_number' => $roundNumber,
                'matches' => array_values($roundMatches),
            ];

            $roundNumber++;
        }

        return $rounds;
    }

    /**
     * Extract player IDs from a match structure (handles single, double, and team formats).
     *
     * @param array $match
     * @return array<int>
     */
    private function getMatchPlayerIds(array $match): array
    {
        $ids = [];

        if (isset($match['participant1_id'])) {
            $ids[] = $match['participant1_id'];
        }
        if (isset($match['participant2_id'])) {
            $ids[] = $match['participant2_id'];
        }
        if (isset($match['team1_players']) && is_array($match['team1_players'])) {
            foreach ($match['team1_players'] as $pid) {
                $ids[] = $pid;
            }
        }
        if (isset($match['team2_players']) && is_array($match['team2_players'])) {
            foreach ($match['team2_players'] as $pid) {
                $ids[] = $pid;
            }
        }

        return $ids;
    }

    /**
     * Resolve participant ID from a MiniTeam and the user→participant lookup map.
     *
     * For single format: the team's only member → participant via user_id.
     * For double format: all team members → participants via user_id.
     *
     * @param  \App\Models\MiniTeam|null  $team
     * @param  array  $userToParticipant  [user_id => participant_id]
     * @return int|null
     */
    private function resolveParticipantId(?\App\Models\MiniTeam $team, array $userToParticipant): ?int
    {
        if (!$team || !$team->relationLoaded('members')) {
            return null;
        }
        foreach ($team->members as $member) {
            $userId = $member->user_id ?? ($member->user?->id ?? null);
            if ($userId !== null && isset($userToParticipant[$userId])) {
                return (int) $userToParticipant[$userId];
            }
        }
        return null;
    }

}
