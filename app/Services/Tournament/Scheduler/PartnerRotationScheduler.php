<?php

namespace App\Services\Tournament\Scheduler;

use App\Services\Tournament\Scheduler\OpponentHistoryTracker;
use App\Services\Tournament\Scheduler\RoundBuilder;

/**
 * Partner Rotation Scheduler.
 *
 * Spec: "Mỗi người ghép cặp với tất cả người còn lại đúng 1 lần"
 *
 * Uses 1-factorization (circle method) for optimal partnership distribution:
 * - n even: n-1 rounds, n/2 partnerships each
 * - n odd: n rounds, (n-1)/2 partnerships each, 1 player sits out per round
 *
 * Then pairs partnerships into doubles matches using RoundBuilder.
 */
class PartnerRotationScheduler
{
    /**
     * Generate a partner rotation schedule for doubles format.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @return array{rounds: array, summary: array}
     */
    public function generate(array $playerIds): array
    {
        $n = count($playerIds);

        if ($n < 4) {
            throw new \InvalidArgumentException('partner_rotation requires at least 4 players, got ' . $n);
        }

        $playerIds = array_values($playerIds);

        // Step 1: 1-factorization — generate all C(n,2) partnerships
        $allPartnerships = $this->generateAllPartnerships($playerIds);

        // Step 2: Build optimal rounds using circle method
        $rawRounds = RoundBuilder::buildPartnerRotationRounds($playerIds);

        // Step 3: Pair partnerships into doubles matches
        $oppHistory = new OpponentHistoryTracker();
        $byeCount = [];
        $rounds = [];
        $allMatches = [];

        foreach ($rawRounds as $roundIdx => $partnerships) {
            $matches = RoundBuilder::pairPartnershipsIntoMatches(
                $partnerships,
                $oppHistory,
                $byeCount
            );
            $rounds[] = [
                'round_number' => $roundIdx + 1,
                'matches' => $matches,
            ];
            foreach ($matches as $m) {
                $allMatches[] = $m;
            }
        }

        // Summary
        $matchesPerPlayer = $this->calculateMatchesPerPlayer($allMatches, $playerIds);

        $unbalancedNotice = null;
        if ($n === 6 || $n === 7) {
            $min = min($matchesPerPlayer);
            $max = max($matchesPerPlayer);
            if ($min !== $max) {
                $unbalancedNotice = "Số trận không đều: {$min}-{$max} trận/người. "
                    . "Với {$n} người, 2 người đánh thêm 1 trận theo spec.";
            }
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'total_partnerships' => count($allPartnerships),
                'matches_per_player' => $matchesPerPlayer,
                'unbalanced_notice' => $unbalancedNotice,
            ],
        ];
    }

    /**
     * Generate all C(n,2) unique player pairs.
     */
    private function generateAllPartnerships(array $playerIds): array
    {
        $partnerships = [];
        $n = count($playerIds);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $partnerships[] = [
                    'p1_id' => $playerIds[$i],
                    'p2_id' => $playerIds[$j],
                ];
            }
        }

        return $partnerships;
    }

    /**
     * Calculate how many matches each player participates in.
     *
     * @param array $allMatches
     * @param array $playerIds
     * @return array<int, int> [participantId => matchCount]
     */
    private function calculateMatchesPerPlayer(array $allMatches, array $playerIds): array
    {
        $counts = array_fill_keys($playerIds, 0);

        foreach ($allMatches as $match) {
            if (!empty($match['is_bye'])) {
                continue;
            }
            foreach ($match['team1_players'] as $pid) {
                if (isset($counts[$pid])) {
                    $counts[$pid]++;
                }
            }
            foreach ($match['team2_players'] as $pid) {
                if (isset($counts[$pid])) {
                    $counts[$pid]++;
                }
            }
        }

        return $counts;
    }
}
