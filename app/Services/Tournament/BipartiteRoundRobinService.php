<?php

namespace App\Services\Tournament;

use Illuminate\Support\Collection;

/**
 * Bipartite Round Robin Scheduler.
 *
 * Generates a schedule where every member of group A plays every member
 * of group B exactly once, using the circle method (fixed group A,
 * rotating group B). BYE slots are automatically balanced — each player
 * rests the same number of times, or at most 1 time difference.
 *
 * Algorithm:
 *   n = max(count(groupA), count(groupB))
 *   Pad the smaller group with null (BYE placeholder) to size n.
 *   Round i: A[j] vs B[(i + j) % n] for j = 0 .. n-1
 *   After each round: rotate B — move last element to front.
 *
 * Output format:
 *   [
 *     [
 *       ['player_a' => 1, 'player_b' => 5],
 *       ['player_a' => 2, 'player_b' => 6],
 *     ],
 *     [...],
 *   ]
 */
class BipartiteRoundRobinService
{
    /**
     * Generate bipartite round-robin schedule.
     *
     * @param Collection|array $groupA  List of group-A player IDs
     * @param Collection|array $groupB  List of group-B player IDs
     * @param bool             $shuffle Randomize order of players before scheduling
     *
     * @return array{
     *   rounds: array,
     *   summary: array{
     *     total_rounds: int,
     *     total_matches: int,
     *     group_a_count: int,
     *     group_b_count: int,
     *     matches_per_a: int,
     *     matches_per_b: int,
     *     group_a_byes: int,
     *     group_b_byes: int,
     *     bye_balanced: bool,
     *     unbalanced_notice: string|null,
     *   }
     * }
     */
    public static function generate(
        Collection|array $groupA,
        Collection|array $groupB,
        bool $shuffle = true
    ): array {
        $aIds = self::toArray($groupA);
        $bIds = self::toArray($groupB);

        if ($shuffle) {
            shuffle($aIds);
            shuffle($bIds);
        }

        $aCount = count($aIds);
        $bCount = count($bIds);
        $n = max($aCount, $bCount);

        if ($n === 0) {
            return [
                'rounds' => [],
                'summary' => [
                    'total_rounds' => 0,
                    'total_matches' => 0,
                    'group_a_count' => 0,
                    'group_b_count' => 0,
                    'matches_per_a' => 0,
                    'matches_per_b' => 0,
                    'group_a_byes' => 0,
                    'group_b_byes' => 0,
                    'bye_balanced' => true,
                    'unbalanced_notice' => null,
                    'bye_count_a' => [],
                    'bye_count_b' => [],
                ],
            ];
        }

        $paddedA = self::padWithNull($aIds, $n);
        $paddedB = self::padWithNull($bIds, $n);

        $rounds = [];
        $byeCountA = array_fill(0, $aCount, 0);
        $byeCountB = array_fill(0, $bCount, 0);

        for ($round = 0; $round < $n; $round++) {
            $roundMatches = [];
            for ($j = 0; $j < $n; $j++) {
                $playerA = $paddedA[$j];
                $playerB = $paddedB[$j];

                if ($playerA === null && $playerB === null) {
                    continue;
                }

                $isBye = ($playerA === null || $playerB === null);

                if ($isBye) {
                    if ($playerA === null && $playerB !== null) {
                        $bOriginalIndex = array_search($playerB, $bIds, true);
                        if ($bOriginalIndex !== false) {
                            $byeCountB[$bOriginalIndex]++;
                        }
                    } elseif ($playerB === null && $playerA !== null) {
                        $aOriginalIndex = array_search($playerA, $aIds, true);
                        if ($aOriginalIndex !== false) {
                            $byeCountA[$aOriginalIndex]++;
                        }
                    }
                }

                $roundMatches[] = [
                    'player_a' => $playerA,
                    'player_b' => $playerB,
                    'is_bye' => $isBye,
                ];
            }

            $rounds[] = $roundMatches;

            $lastB = array_pop($paddedB);
            array_unshift($paddedB, $lastB);
        }

        $matchesPerA = $bCount; // Each A plays all B
        $matchesPerB = $aCount; // Each B plays all A
        $groupAByes = $aCount > 0 ? ($n - $bCount) : 0;
        $groupBByes = $bCount > 0 ? ($n - $aCount) : 0;

        $totalMatches = $aCount * $bCount;

        $byeBalanced = self::isByeBalanced($byeCountA, $byeCountB);

        $unbalancedNotice = null;
        if (!empty($byeCountA)) {
            $minA = min($byeCountA);
            $maxA = max($byeCountA);
            if ($minA !== $maxA) {
                $unbalancedNotice = "BYE nam chênh lệch: {$minA}-{$maxA} lần.";
            }
        }
        if (!empty($byeCountB)) {
            $minB = min($byeCountB);
            $maxB = max($byeCountB);
            if ($minB !== $maxB) {
                $notice = "BYE nữ chênh lệch: {$minB}-{$maxB} lần.";
                $unbalancedNotice = $unbalancedNotice
                    ? "{$unbalancedNotice} {$notice}"
                    : $notice;
            }
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => $n,
                'total_matches' => $totalMatches,
                'group_a_count' => $aCount,
                'group_b_count' => $bCount,
                'matches_per_a' => $matchesPerA,
                'matches_per_b' => $matchesPerB,
                'group_a_byes' => $groupAByes,
                'group_b_byes' => $groupBByes,
                'bye_balanced' => $byeBalanced,
                'unbalanced_notice' => $unbalancedNotice,
                'bye_count_a' => $byeCountA,
                'bye_count_b' => $byeCountB,
            ],
        ];
    }

    /**
     * Convert input to plain array (handles Collection or array).
     */
    private static function toArray(Collection|array $input): array
    {
        return $input instanceof Collection ? $input->values()->all() : array_values($input);
    }

    /**
     * Pad an array to target size with null values.
     */
    private static function padWithNull(array $ids, int $targetSize): array
    {
        $padded = array_values($ids);
        while (count($padded) < $targetSize) {
            $padded[] = null;
        }
        return $padded;
    }

    /**
     * Check if BYE distribution is balanced (max 1 difference per player).
     */
    private static function isByeBalanced(
        array $byeCountA,
        array $byeCountB
    ): bool {
        if (!empty($byeCountA)) {
            $minA = min($byeCountA);
            $maxA = max($byeCountA);
            if ($maxA - $minA > 1) {
                return false;
            }
        }
        if (!empty($byeCountB)) {
            $minB = min($byeCountB);
            $maxB = max($byeCountB);
            if ($maxB - $minB > 1) {
                return false;
            }
        }
        return true;
    }


}
