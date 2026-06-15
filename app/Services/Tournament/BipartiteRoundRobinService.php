<?php

namespace App\Services\Tournament;

use Illuminate\Support\Collection;

/**
 * Bipartite Round Robin Scheduler.
 *
 * Generates a schedule where every member of group A plays every member
 * of group B exactly once.
 *
 * Algorithm: Fixed group B (females) across columns, rotate group A (males)
 * across rows using the formula: in round r, male at index (r - j) % n
 * pairs with female at column j.
 *
 * Result:
 *   n = max(count(groupA), count(groupB)) rounds
 *   - Each player plays min(m, f) matches
 *   - Each player sits out |m - f| rounds (BYE)
 *   - Every male-female pair meets exactly once
 *   - No player ever gets 0 actual matches
 */
class BipartiteRoundRobinService
{
    public static function generate(
        $groupA,
        $groupB,
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

        if ($aCount === 0 || $bCount === 0) {
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
                ],
            ];
        }

        $n = max($aCount, $bCount);
        $rounds = [];

        for ($round = 0; $round < $n; $round++) {
            $roundMatches = [];

            for ($col = 0; $col < $bCount; $col++) {
                $row = ($round - $col + $n) % $n;

                if ($row < $aCount) {
                    $playerA = $aIds[$row];
                    $playerB = $bIds[$col];
                    $roundMatches[] = [
                        'player_a' => $playerA,
                        'player_b' => $playerB,
                        'is_bye' => false,
                    ];
                } else {
                    // row >= aCount: female gets a BYE match
                    $roundMatches[] = [
                        'player_a' => null,
                        'player_b' => $bIds[$col],
                        'is_bye' => true,
                    ];
                }
            }

            $rounds[] = $roundMatches;
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => $n,
                'total_matches' => $aCount * $bCount,
                'group_a_count' => $aCount,
                'group_b_count' => $bCount,
                'matches_per_a' => $bCount,
                'matches_per_b' => $aCount,
                'group_a_byes' => $aCount > 0 ? ($n - $bCount) : 0,
                'group_b_byes' => $bCount > 0 ? ($n - $aCount) : 0,
            ],
        ];
    }

    private static function toArray($input): array
    {
        return $input instanceof Collection
            ? $input->values()->all()
            : array_values($input);
    }
}
