<?php

namespace App\Services\Tournament\Scheduler;

use App\Services\Tournament\Scheduler\OpponentHistoryTracker;
use App\Services\Tournament\Scheduler\RoundBuilder;

/**
 * Mixed Gender Scheduler.
 *
 * Spec: "Mỗi nam ghép với mỗi nữ đúng 1 lần"
 *
 * Uses BipartiteRoundRobinService for optimal partnership distribution,
 * then pairs partnerships into doubles matches using RoundBuilder.
 */
class MixedGenderScheduler
{
    /**
     * Generate a mixed gender schedule for doubles format.
     *
     * @param array $maleIds   Array of MiniParticipant IDs (male group)
     * @param array $femaleIds Array of MiniParticipant IDs (female group)
     * @return array{rounds: array, summary: array}
     */
    public function generate(array $maleIds, array $femaleIds): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female');
        }

        // Use BipartiteRoundRobinService for optimal round generation
        $bipartite = \App\Services\Tournament\BipartiteRoundRobinService::generate(
            $maleIds,
            $femaleIds,
            false // no shuffle — deterministic
        );

        // Convert Bipartite output to our partnership format
        $rounds = [];
        $allMatches = [];
        $oppHistory = new OpponentHistoryTracker();
        $byeCount = [];

        foreach ($bipartite['rounds'] as $roundIdx => $bRound) {
            // Convert (player_a, player_b, is_bye) entries to partnerships
            $partnerships = [];
            foreach ($bRound as $entry) {
                if (!empty($entry['is_bye'])) {
                    continue; // BYE entries don't form partnerships
                }
                $partnerships[] = [
                    'male_id' => $entry['player_a'],
                    'female_id' => $entry['player_b'],
                ];
            }

            // Pair partnerships into doubles matches
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

        // Compute stats
        $maleMatchCount = array_fill_keys($maleIds, 0);
        $femaleMatchCount = array_fill_keys($femaleIds, 0);

        foreach ($allMatches as $match) {
            if (!empty($match['is_bye'])) {
                continue;
            }
            foreach ($match['team1_players'] as $pid) {
                if (isset($maleMatchCount[$pid])) {
                    $maleMatchCount[$pid]++;
                } elseif (isset($femaleMatchCount[$pid])) {
                    $femaleMatchCount[$pid]++;
                }
            }
            foreach ($match['team2_players'] as $pid) {
                if (isset($maleMatchCount[$pid])) {
                    $maleMatchCount[$pid]++;
                } elseif (isset($femaleMatchCount[$pid])) {
                    $femaleMatchCount[$pid]++;
                }
            }
        }

        $unbalancedNotice = null;
        if ($m !== $f) {
            $unbalancedNotice = "{$m} nam sẽ đánh {$f} trận, {$f} nữ sẽ đánh {$m} trận.";
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'total_partnerships' => $m * $f,
                'male_matches' => $maleMatchCount,
                'female_matches' => $femaleMatchCount,
                'matches_per_male' => $f,
                'matches_per_female' => $m,
                'unbalanced_notice' => $unbalancedNotice,
            ],
        ];
    }
}
