<?php

namespace App\Services\Tournament\Scheduler;

use App\Services\Tournament\Scheduler\OpponentHistoryTracker;
use App\Services\Tournament\Scheduler\RoundBuilder;

/**
 * Rank Pairing Scheduler (A/B groups).
 *
 * Logic is identical to MixedGenderScheduler, with:
 *   Male → A
 *   Female → B
 *
 * Spec: "Mỗi A ghép với mỗi B đúng 1 lần"
 */
class RankPairingScheduler
{
    /**
     * Generate a rank pairing schedule for doubles format.
     *
     * @param array $aIds Array of MiniParticipant IDs (group A)
     * @param array $bIds Array of MiniParticipant IDs (group B)
     * @return array{rounds: array, summary: array}
     */
    public function generate(array $aIds, array $bIds): array
    {
        $na = count($aIds);
        $nb = count($bIds);

        if ($na < 1 || $nb < 1) {
            throw new \InvalidArgumentException('rank_pairing requires at least 1 player in each group');
        }

        // BipartiteRoundRobinService treats first param as group A
        $bipartite = \App\Services\Tournament\BipartiteRoundRobinService::generate(
            $aIds,
            $bIds,
            false
        );

        $rounds = [];
        $allMatches = [];
        $oppHistory = new OpponentHistoryTracker();
        $byeCount = [];

        foreach ($bipartite['rounds'] as $roundIdx => $bRound) {
            $partnerships = [];
            foreach ($bRound as $entry) {
                if (!empty($entry['is_bye'])) {
                    continue;
                }
                $partnerships[] = [
                    'a_id' => $entry['player_a'],
                    'b_id' => $entry['player_b'],
                ];
            }

            $matches = RoundBuilder::pairPartnershipsIntoMatches(
                $partnerships,
                $oppHistory,
                $byeCount
            );

            $rounds[] = [
                'round_number' => $roundIdx + 1,
                'matches' => $matches,
            ];

            foreach ($matches as $matchItem) {
                $allMatches[] = $matchItem;
            }
        }

        // Stats: use theoretical counts from spec, not actual match counting.
        // "Mỗi A ghép với mỗi B đúng 1 lần" → each A plays nb matches,
        // each B plays na matches. This is guaranteed by BipartiteRoundRobinService.
        $aMatchCount = [];
        foreach ($aIds as $aid) {
            $aMatchCount[$aid] = $nb;
        }
        $bMatchCount = [];
        foreach ($bIds as $bid) {
            $bMatchCount[$bid] = $na;
        }

        $unbalancedNotice = null;
        if ($na !== $nb) {
            $unbalancedNotice = "{$na} A sẽ đánh {$nb} trận, {$nb} B sẽ đánh {$na} trận.";
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'total_partnerships' => $na * $nb,
                'a_matches' => $aMatchCount,
                'b_matches' => $bMatchCount,
                'matches_per_a' => $nb,
                'matches_per_b' => $na,
                'unbalanced_notice' => $unbalancedNotice,
            ],
        ];
    }
}
