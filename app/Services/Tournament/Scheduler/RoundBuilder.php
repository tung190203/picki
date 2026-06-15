<?php

namespace App\Services\Tournament\Scheduler;

use App\Services\Tournament\Scheduler\OpponentHistoryTracker;

/**
 * Shared round-building logic for all scheduler types.
 *
 * For PARTNER ROTATION: 1-factorization for even n, greedy packing for odd n.
 * For BIPARTITE (Mixed/RankPairing): circle method with larger group fixed.
 */
class RoundBuilder
{
    /**
     * Build rounds for PARTNER ROTATION.
     *
     * For even n: n-1 rounds, each with n/2 partnerships (circle method).
     * For odd n: n rounds, each with (n-1)/2 partnerships (greedy packing).
     */
    public static function buildPartnerRotationRounds(array $playerIds): array
    {
        $n = count($playerIds);
        $ids = array_values($playerIds);

        if ($n < 4) {
            throw new \InvalidArgumentException('At least 4 players required');
        }

        if ($n % 2 !== 0) {
            // Odd n: greedy packing of all C(n,2) partnerships into n rounds.
            $allPairs = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $allPairs[] = ['p1_id' => $ids[$i], 'p2_id' => $ids[$j]];
                }
            }

            return self::greedyPackRounds($allPairs, $ids);
        }

        // Even n: circle method (1-factorization) — n-1 rounds.
        $numRounds = $n - 1;
        $rounds = [];

        for ($round = 0; $round < $numRounds; $round++) {
            $partnerships = [];

            $rotating = array_slice($ids, 1); // players 1..n-1
            $rotLen = $n - 1;

            if ($round > 0) {
                $shift = $round % $rotLen;
                $rotating = array_merge(
                    array_slice($rotating, -$shift),
                    array_slice($rotating, 0, $rotLen - $shift)
                );
            }

            // (0, rotating[0]), (rotating[1], rotating[rotLen-1]), (rotating[2], rotating[rotLen-2]), ...
            $partnerships[] = ['p1_id' => $ids[0], 'p2_id' => $rotating[0]];

            for ($i = 1; $i < $rotLen / 2; $i++) {
                $partnerships[] = [
                    'p1_id' => $rotating[$i],
                    'p2_id' => $rotating[$rotLen - $i],
                ];
            }

            $rounds[] = $partnerships;
        }

        return $rounds;
    }

    /**
     * Greedy-packing: distribute all partnerships into rounds with no player conflict.
     * Used for odd-n partner rotation.
     */
    private static function greedyPackRounds(array $allPairs, array $playerIds): array
    {
        $n = count($playerIds);
        $numRounds = $n; // each player sits out once
        $pairsPerRound = ($n - 1) / 2;

        $remaining = $allPairs;
        $rounds = [];

        for ($roundIdx = 0; $roundIdx < $numRounds; $roundIdx++) {
            $roundPairs = [];
            $playersUsed = [];

            // Greedy: pick as many non-conflicting partnerships as possible
            foreach ($remaining as $idx => $pair) {
                $p1 = $pair['p1_id'];
                $p2 = $pair['p2_id'];

                if (in_array($p1, $playersUsed, true) || in_array($p2, $playersUsed, true)) {
                    continue;
                }

                $roundPairs[] = $pair;
                $playersUsed[] = $p1;
                $playersUsed[] = $p2;
                unset($remaining[$idx]);

                if (count($roundPairs) >= $pairsPerRound) {
                    break;
                }
            }

            // Deadlock recovery: pick any remaining non-conflicting pair
            if (count($roundPairs) < $pairsPerRound) {
                foreach ($remaining as $idx => $pair) {
                    $p1 = $pair['p1_id'];
                    $p2 = $pair['p2_id'];

                    if (in_array($p1, $playersUsed, true) || in_array($p2, $playersUsed, true)) {
                        continue;
                    }

                    $roundPairs[] = $pair;
                    $playersUsed[] = $p1;
                    $playersUsed[] = $p2;
                    unset($remaining[$idx]);

                    if (count($roundPairs) >= $pairsPerRound) {
                        break;
                    }
                }
            }

            $rounds[] = $roundPairs;
        }

        return $rounds;
    }

    /**
     * Build rounds for BIPARTITE (Mixed/RankPairing).
     *
     * Strategy:
     * - Equal groups: circle method (optimal).
     * - larger % smaller == 0: circle method with larger fixed.
     * - larger % smaller != 0 AND smaller % (larger - smaller) == 0: circle method with smaller fixed.
     * - Otherwise: greedy packing.
     */
    public static function buildBipartiteRounds(array $maleIds, array $femaleIds): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            return [];
        }

        $males = array_values($maleIds);
        $females = array_values($femaleIds);

        if ($m === $f) {
            return self::circleBipartiteRoundsFixedLarger($males, $females);
        }

        $larger = max($m, $f);
        $smaller = min($m, $f);

        if ($larger % $smaller === 0) {
            if ($m >= $f) {
                return self::circleBipartiteRoundsFixedLarger($males, $females);
            } else {
                return self::circleBipartiteRoundsFixedLarger($females, $males, true);
            }
        }

        // larger % smaller != 0: can we fix the smaller?
        // Check if smaller % (larger - smaller) == 0 (the remaining players after pairing)
        $remaining = $larger - $smaller;
        if ($remaining > 0 && $smaller % $remaining === 0) {
            // Fix the smaller group
            if ($m >= $f) {
                return self::circleBipartiteRoundsFixedSmaller($males, $females);
            } else {
                return self::circleBipartiteRoundsFixedSmaller($females, $males, true);
            }
        }

        // Fallback: greedy packing (correct for all cases including 4x3, 3x5, etc.)
        return self::greedyPackBipartiteRounds($males, $females);
    }

    /**
     * Circle method where the first param is the larger group (fixed), second is smaller (rotating).
     *
     * @param array $larger Fixed group
     * @param array $smaller Rotating group
     * @param bool $swapKeys If true, swap male/female key names
     */
    private static function circleBipartiteRoundsFixedLarger(array $larger, array $smaller, bool $swapKeys = false): array
    {
        $numRounds = count($larger);
        $rotLen = count($smaller);
        $rounds = [];

        for ($round = 0; $round < $numRounds; $round++) {
            $partnerships = [];
            $rotated = $smaller;

            if ($round > 0) {
                $shift = $round % $rotLen;
                $rotated = array_merge(
                    array_slice($smaller, -$shift),
                    array_slice($smaller, 0, $rotLen - $shift)
                );
            }

            for ($i = 0; $i < $numRounds; $i++) {
                $j = $i % $rotLen;
                if ($swapKeys) {
                    $partnerships[] = [
                        'female_id' => $larger[$i],
                        'male_id' => $rotated[$j],
                    ];
                } else {
                    $partnerships[] = [
                        'male_id' => $larger[$i],
                        'female_id' => $rotated[$j],
                    ];
                }
            }

            $rounds[] = $partnerships;
        }

        return $rounds;
    }

    /**
     * Circle method where the first param is the smaller group (fixed), second is larger (rotating).
     *
     * @param array $fixed Fixed (smaller) group
     * @param array $rotating Rotating (larger) group
     * @param bool $swapKeys If true, swap male/female key names
     */
    private static function circleBipartiteRoundsFixedSmaller(array $fixed, array $rotating, bool $swapKeys = false): array
    {
        $numRounds = count($rotating);
        $fixedLen = count($fixed);
        $rounds = [];

        for ($round = 0; $round < $numRounds; $round++) {
            $partnerships = [];
            $rot = $rotating;

            if ($round > 0) {
                $shift = $round % $numRounds;
                $rot = array_merge(
                    array_slice($rotating, -$shift),
                    array_slice($rotating, 0, $numRounds - $shift)
                );
            }

            for ($i = 0; $i < $fixedLen; $i++) {
                $j = $i % $numRounds;
                if ($swapKeys) {
                    $partnerships[] = [
                        'female_id' => $fixed[$i],
                        'male_id' => $rot[$j],
                    ];
                } else {
                    $partnerships[] = [
                        'male_id' => $fixed[$i],
                        'female_id' => $rot[$j],
                    ];
                }
            }

            $rounds[] = $partnerships;
        }

        return $rounds;
    }

    /**
     * Greedy-packing for unequal bipartite groups.
     *
     * Generates all m×n unique partnerships, then packs them into rounds
     * with no player conflict. Each round has min(m,n) partnerships.
     * When m != n, the larger group gets BYEs in some rounds.
     * Produces max(m,n) rounds to accommodate all partnerships.
     */
    private static function greedyPackBipartiteRounds(array $males, array $females): array
    {
        $m = count($males);
        $f = count($females);

        // Generate all partnerships
        $allPartnerships = [];
        foreach ($males as $mid) {
            foreach ($females as $fid) {
                $allPartnerships[] = ['male_id' => $mid, 'female_id' => $fid];
            }
        }

        $numRounds = max($m, $f);
        $pairsPerRound = min($m, $f);
        $remaining = $allPartnerships;
        $rounds = [];

        for ($roundIdx = 0; $roundIdx < $numRounds; $roundIdx++) {
            $roundPairs = [];
            $malesUsed = [];
            $femalesUsed = [];

            // Greedy: pick as many non-conflicting partnerships as possible
            foreach ($remaining as $idx => $pair) {
                if (in_array($pair['male_id'], $malesUsed, true)
                    || in_array($pair['female_id'], $femalesUsed, true)) {
                    continue;
                }

                $roundPairs[] = $pair;
                $malesUsed[] = $pair['male_id'];
                $femalesUsed[] = $pair['female_id'];
                unset($remaining[$idx]);

                if (count($roundPairs) >= $pairsPerRound) {
                    break;
                }
            }

            // Fill remaining with BYE partnerships for unused players
            $usedMales = array_flip($malesUsed);
            $usedFemales = array_flip($femalesUsed);

            foreach ($males as $mid) {
                if (!isset($usedMales[$mid])) {
                    $roundPairs[] = ['male_id' => $mid, 'female_id' => null, '_is_bye_slot' => true];
                    break;
                }
            }
            foreach ($females as $fid) {
                if (!isset($usedFemales[$fid])) {
                    $roundPairs[] = ['female_id' => $fid, 'male_id' => null, '_is_bye_slot' => true];
                    break;
                }
            }

            $remaining = array_values($remaining);
            $rounds[] = $roundPairs;
        }

        return $rounds;
    }

    /**
     * Build rounds for A/B (RankPairing) — identical to bipartite.
     */
    public static function buildRankPairingRounds(array $aIds, array $bIds): array
    {
        $na = count($aIds);
        $nb = count($bIds);

        if ($na < 1 || $nb < 1) {
            return [];
        }

        $aList = array_values($aIds);
        $bList = array_values($bIds);

        if ($na === $nb) {
            // Equal: circle method — na rounds, na partnerships each
            $numRounds = $na;
            $rounds = [];

            for ($round = 0; $round < $numRounds; $round++) {
                $partnerships = [];
                $rotated = $bList;

                if ($round > 0) {
                    $shift = $round % $nb;
                    $rotated = array_merge(
                        array_slice($bList, -$shift),
                        array_slice($bList, 0, $nb - $shift)
                    );
                }

                for ($i = 0; $i < $na; $i++) {
                    $partnerships[] = ['a_id' => $aList[$i], 'b_id' => $rotated[$i]];
                }

                $rounds[] = $partnerships;
            }

            return $rounds;
        }

        // Unequal: greedy packing
        return self::greedyPackRankPairingRounds($aList, $bList);
    }

    private static function greedyPackRankPairingRounds(array $aList, array $bList): array
    {
        $na = count($aList);
        $nb = count($bList);

        $allPartnerships = [];
        foreach ($aList as $aid) {
            foreach ($bList as $bid) {
                $allPartnerships[] = ['a_id' => $aid, 'b_id' => $bid];
            }
        }

        $numRounds = max($na, $nb);
        $pairsPerRound = min($na, $nb);
        $remaining = $allPartnerships;
        $rounds = [];

        for ($roundIdx = 0; $roundIdx < $numRounds; $roundIdx++) {
            $roundPairs = [];
            $aUsed = [];
            $bUsed = [];

            foreach ($remaining as $idx => $pair) {
                if (in_array($pair['a_id'], $aUsed, true)
                    || in_array($pair['b_id'], $bUsed, true)) {
                    continue;
                }

                $roundPairs[] = $pair;
                $aUsed[] = $pair['a_id'];
                $bUsed[] = $pair['b_id'];
                unset($remaining[$idx]);

                if (count($roundPairs) >= $pairsPerRound) {
                    break;
                }
            }

            // Fill BYE slots for unused players
            $usedAMap = array_flip($aUsed);
            $usedBMap = array_flip($bUsed);

            foreach ($aList as $aid) {
                if (!isset($usedAMap[$aid])) {
                    $roundPairs[] = ['a_id' => $aid, 'b_id' => null, '_is_bye_slot' => true];
                    break;
                }
            }
            foreach ($bList as $bid) {
                if (!isset($usedBMap[$bid])) {
                    $roundPairs[] = ['b_id' => $bid, 'a_id' => null, '_is_bye_slot' => true];
                    break;
                }
            }

            $remaining = array_values($remaining);
            $rounds[] = $roundPairs;
        }

        return $rounds;
    }

    /**
     * Pair partnerships into doubles matches within a round.
     *
     * Two partnerships face each other per match.
     * Partnerships with shared players CANNOT be opponents.
     * Tracks opponent history to minimize repeated matchups.
     * When odd number of partnerships, one gets a BYE.
     */
    public static function pairPartnershipsIntoMatches(
        array $partnerships,
        ?OpponentHistoryTracker $tracker = null,
        array &$byeCount = []
    ): array {
        $matches = [];
        if (empty($partnerships)) {
            return $matches;
        }

        $indexed = [];
        foreach ($partnerships as $p) {
            $key = self::partnershipKey($p);
            $indexed[] = ['p' => $p, 'key' => $key, 'paired' => false];
        }

        $n = count($indexed);

        // Fewest BYE rounds first (fairness) — only if tracker is available
        if ($tracker !== null) {
            usort($indexed, function ($a, $b) use ($byeCount) {
                return ($byeCount[$a['key']] ?? 0) - ($byeCount[$b['key']] ?? 0);
            });
        }

        for ($i = 0; $i < $n; $i++) {
            if ($indexed[$i]['paired']) {
                continue;
            }

            $p1 = $indexed[$i]['p'];
            $p1Key = $indexed[$i]['key'];
            $indexed[$i]['paired'] = true;
            $p1Players = self::getPartnershipPlayers($p1);

            $bestIdx = null;
            $bestScore = PHP_INT_MAX;

            for ($j = 0; $j < $n; $j++) {
                if ($indexed[$j]['paired']) {
                    continue;
                }
                $p2 = $indexed[$j]['p'];
                $p2Key = $indexed[$j]['key'];
                $p2Players = self::getPartnershipPlayers($p2);

                // Cannot pair if any player is shared
                if (!empty(array_intersect($p1Players, $p2Players))) {
                    continue;
                }

                $score = $tracker !== null ? $tracker->getEncounterCount($p1Key, $p2Key) : 0;
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $j;
                }
            }

            if ($bestIdx !== null) {
                $p2 = $indexed[$bestIdx]['p'];
                $p2Key = $indexed[$bestIdx]['key'];
                $indexed[$bestIdx]['paired'] = true;

                if ($tracker !== null) {
                    $tracker->increment($p1Key, $p2Key);
                }

                $matches[] = [
                    'team1_players' => self::partnershipToPlayers($p1),
                    'team2_players' => self::partnershipToPlayers($p2),
                    'is_bye' => false,
                ];
            } else {
                $byeCount[$p1Key] = ($byeCount[$p1Key] ?? 0) + 1;

                $matches[] = [
                    'team1_players' => self::partnershipToPlayers($p1),
                    'team2_players' => [],
                    'is_bye' => true,
                ];
            }
        }

        return $matches;
    }

    public static function getPartnershipPlayers(array $p): array
    {
        if (isset($p[0], $p[1]) && is_int($p[0]) && is_int($p[1])) {
            return [(int) $p[0], (int) $p[1]];
        }
        if (isset($p['participant1_id'], $p['participant2_id'])) {
            return [(int) $p['participant1_id'], (int) $p['participant2_id']];
        }
        if (isset($p['p1_id'], $p['p2_id'])) {
            return [(int) $p['p1_id'], (int) $p['p2_id']];
        }
        if (isset($p['male_id'], $p['female_id'])) {
            return [(int) $p['male_id'], (int) $p['female_id']];
        }
        if (isset($p['a_id'], $p['b_id'])) {
            return [(int) $p['a_id'], (int) $p['b_id']];
        }
        return [];
    }

    public static function partnershipToPlayers(array $p): array
    {
        return array_values(self::getPartnershipPlayers($p));
    }

    public static function partnershipKey(array $p): string
    {
        $players = self::getPartnershipPlayers($p);
        sort($players);
        return implode('-', $players);
    }
}
