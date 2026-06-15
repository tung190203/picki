<?php

namespace App\Services\Tournament\Scheduler;

use App\Services\Tournament\BipartiteRoundRobinService;

/**
 * Mixed Gender Scheduler.
 *
 * Spec: "Mỗi nam ghép với mỗi nữ đúng 1 lần"
 *
 * Algorithm:
 *   1. BipartiteRoundRobinService generates all m×f unique MF partnerships.
 *   2. Each round has f courts (one per female). Pair f partnerships into doubles.
 *   3. ByeAllocator: sort by highest cumulative bye count so players with more
 *      byes get first pick at partners, reducing their bye chance.
 */
class MixedGenderScheduler
{
    public function generate(array $maleIds, array $femaleIds): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female');
        }

        $maleIds = array_values($maleIds);
        $femaleIds = array_values($femaleIds);

        $bipartite = BipartiteRoundRobinService::generate($maleIds, $femaleIds, false);

        $rounds = [];
        $allMatches = [];

        // Per-player real match appearances (non-BYE)
        $realMatchesPerMale = array_fill_keys($maleIds, 0);
        $realMatchesPerFemale = array_fill_keys($femaleIds, 0);

        // Cumulative bye count per male (for fairness)
        $maleByeCount = array_fill_keys($maleIds, 0);

        $byeAllocator = new ByeAllocator();

        foreach ($bipartite['rounds'] as $roundIdx => $bRound) {
            $fullPartnerships = [];
            $byeSlots = [];

            foreach ($bRound as $entry) {
                if (!empty($entry['is_bye'])) {
                    $byeSlots[] = ['female' => (int) $entry['player_b']];
                } else {
                    $fullPartnerships[] = [
                        'male' => (int) $entry['player_a'],
                        'female' => (int) $entry['player_b'],
                    ];
                }
            }

            $roundMatches = $this->pairPartnershipsIntoMatches(
                $fullPartnerships,
                $byeSlots,
                $maleByeCount,
                $byeAllocator,
                $realMatchesPerMale,
                $realMatchesPerFemale
            );

            foreach ($roundMatches as $match) {
                $allMatches[] = $match;
            }

            $rounds[] = [
                'round_number' => $roundIdx + 1,
                'matches' => $roundMatches,
            ];
        }

        $realMatchCount = count(array_filter($allMatches, fn($m) => empty($m['is_bye'])));
        $byeMatchCount = count($allMatches) - $realMatchCount;

        // male_matches / female_matches = partnership count (per spec, for backward compat)
        $partnershipsPerMale = [];
        foreach ($maleIds as $mid) { $partnershipsPerMale[$mid] = $f; }
        $partnershipsPerFemale = [];
        foreach ($femaleIds as $fid) { $partnershipsPerFemale[$fid] = $m; }

        $unbalancedNotice = null;
        if ($m !== $f) {
            $unbalancedNotice = "{$m} nam sẽ đánh {$f} trận, {$f} nữ sẽ đánh {$m} trận.";
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'total_real_matches' => $realMatchCount,
                'total_bye_matches' => $byeMatchCount,
                'total_partnerships' => $m * $f,
                'male_matches' => $partnershipsPerMale,
                'female_matches' => $partnershipsPerFemale,
                'partnerships_per_male' => $f,
                'partnerships_per_female' => $m,
                'real_matches_per_male' => $realMatchesPerMale,
                'real_matches_per_female' => $realMatchesPerFemale,
                'unbalanced_notice' => $unbalancedNotice,
            ],
        ];
    }

    private function pairPartnershipsIntoMatches(
        array $fullPartnerships,
        array $byeSlots,
        array &$maleByeCount,
        ByeAllocator $byeAllocator,
        array &$realMatchesPerMale,
        array &$realMatchesPerFemale
    ): array {
        $roundMatches = [];

        if (empty($fullPartnerships)) {
            foreach ($byeSlots as $slot) {
                $roundMatches[] = [
                    'team1_players' => [$slot['female']],
                    'team2_players' => [],
                    'is_bye' => true,
                ];
            }
            return $roundMatches;
        }

        $indexed = [];
        foreach ($fullPartnerships as $p) {
            $key = self::partnershipKey($p['male'], $p['female']);
            $indexed[] = ['p' => $p, 'key' => $key, 'paired' => false];
        }

        // Process high-bye-count males first so they get first pick at finding partners
        usort($indexed, function ($a, $b) use (&$maleByeCount) {
            $aScore = $maleByeCount[$a['p']['male']];
            $bScore = $maleByeCount[$b['p']['male']];
            if ($aScore !== $bScore) {
                return $bScore - $aScore;
            }
            return mt_rand(-1, 1);
        });

        for ($i = 0; $i < count($indexed); $i++) {
            if ($indexed[$i]['paired']) {
                continue;
            }

            $p1 = $indexed[$i]['p'];
            $p1Key = $indexed[$i]['key'];
            $indexed[$i]['paired'] = true;
            $p1Players = [$p1['male'], $p1['female']];

            $bestIdx = null;
            for ($j = 0; $j < count($indexed); $j++) {
                if ($indexed[$j]['paired']) {
                    continue;
                }
                $p2Players = [$indexed[$j]['p']['male'], $indexed[$j]['p']['female']];
                if (!empty(array_intersect($p1Players, $p2Players))) {
                    continue;
                }
                $bestIdx = $j;
                break;
            }

            if ($bestIdx !== null) {
                $p2 = $indexed[$bestIdx]['p'];
                $indexed[$bestIdx]['paired'] = true;

                $match = [
                    'team1_players' => $p1Players,
                    'team2_players' => [$p2['male'], $p2['female']],
                    'is_bye' => false,
                ];
                $roundMatches[] = $match;

                $realMatchesPerMale[$p1['male']]++;
                $realMatchesPerFemale[$p1['female']]++;
                $realMatchesPerMale[$p2['male']]++;
                $realMatchesPerFemale[$p2['female']]++;
            } else {
                $byeAllocator->recordBye($p1Key, $p1Players);
                $maleByeCount[$p1['male']]++;

                $match = [
                    'team1_players' => $p1Players,
                    'team2_players' => [],
                    'is_bye' => true,
                ];
                $roundMatches[] = $match;
            }
        }

        foreach ($byeSlots as $slot) {
            $roundMatches[] = [
                'team1_players' => [$slot['female']],
                'team2_players' => [],
                'is_bye' => true,
            ];
        }

        return $roundMatches;
    }

    private static function partnershipKey(int $a, int $b): string
    {
        $min = min($a, $b);
        $max = max($a, $b);
        return "{$min}-{$max}";
    }
}
