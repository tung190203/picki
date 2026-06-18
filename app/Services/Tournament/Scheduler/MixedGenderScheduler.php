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

        // === Player bye stats ===
        $minCount = min($m, $f);
        $maxCount = max($m, $f);
        $byePerPlayer = $maxCount - $minCount;
        $totalPlayerByes = $maxCount * $byePerPlayer;

        $largerGroup = $m > $f ? 'male' : 'female';
        $largerIds = $m > $f ? $maleIds : $femaleIds;

        $byeAllocator = new ByeAllocator();

        foreach ($bipartite['rounds'] as $roundIdx => $bRound) {
            $fullPartnerships = [];
            $byeSlots = [];

            foreach ($bRound as $entry) {
                if (!empty($entry['is_bye'])) {
                    $byeSlots[] = $entry;
                } else {
                    $fullPartnerships[] = [
                        'male' => (int) $entry['player_a'],
                        'female' => (int) $entry['player_b'],
                    ];
                }
            }

            // Determine player byes directly from BRS output:
            // players in larger group that are NOT in this round's fullPartnerships
            $maleInRound = array_column($fullPartnerships, 'male');
            $femaleInRound = array_column($fullPartnerships, 'female');

            $playerByesInRound = [];
            foreach ($largerIds as $playerId) {
                $inPartnership = ($largerGroup === 'male')
                    ? in_array($playerId, $maleInRound)
                    : in_array($playerId, $femaleInRound);

                if (!$inPartnership) {
                    $playerByesInRound[] = [
                        'player_id' => $playerId,
                        'group' => $largerGroup,
                        'is_player_bye' => true,
                    ];
                }
            }

            $roundMatches = $this->pairPartnershipsIntoMatches(
                $fullPartnerships,
                $byeSlots,
                $playerByesInRound,
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
                'player_byes' => $playerByesInRound,
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
                'total_partnerships' => $m * $f,
                'male_matches' => $partnershipsPerMale,
                'female_matches' => $partnershipsPerFemale,
                'partnerships_per_male' => $f,
                'partnerships_per_female' => $m,
                'real_matches_per_male' => $realMatchesPerMale,
                'real_matches_per_female' => $realMatchesPerFemale,
                'unbalanced_notice' => $unbalancedNotice,
                // Player bye
                'total_player_byes' => $totalPlayerByes,
                'player_bye_per_player' => $byePerPlayer,
                'player_bye_group' => $largerGroup,
                'player_byes_per_male' => $m > $f ? $byePerPlayer : 0,
                'player_byes_per_female' => $f > $m ? $byePerPlayer : 0,
                // Partnership bye: max x (min % 2)
                'total_partnership_byes' => $maxCount * ($minCount % 2),
                // Backward compat: total bye matches (all matches with is_bye=true, both types)
                'total_bye_matches' => $byeMatchCount,
            ],
        ];
    }

    private function pairPartnershipsIntoMatches(
        array $fullPartnerships,
        array $byeSlots,
        array $playerByesInRound,
        array &$maleByeCount,
        ByeAllocator $byeAllocator,
        array &$realMatchesPerMale,
        array &$realMatchesPerFemale
    ): array {
        $roundMatches = [];

        if (empty($fullPartnerships)) {
            foreach ($byeSlots as $slot) {
                $roundMatches[] = [
                    'team1_players' => [(int) $slot['player_b']],
                    'team2_players' => [],
                    'is_bye' => true,
                    'bye_type' => 'partnership',
                ];
            }
            return $roundMatches;
        }

        // Pair each partnership with the next compatible one (greedy, forward-only).
        // A match uses exactly 2 partnerships that don't share any players.
        // Partnerships left without a partner become partnership byes.
        $indexed = [];
        foreach ($fullPartnerships as $p) {
            $key = self::partnershipKey($p['male'], $p['female']);
            $indexed[] = ['p' => $p, 'key' => $key, 'paired' => false];
        }

        // Group females: pair females[i] with females[i+1] when count is even.
        // This ensures each round has floor(f/2) matches without intra-round conflicts.
        $femaleCount = count($byeSlots) + count(array_column($fullPartnerships, 'female'));
        $females = array_unique(array_column($fullPartnerships, 'female'));
        sort($females);
        $pairedFemales = [];
        for ($i = 0; $i + 1 < count($females); $i += 2) {
            $pairedFemales[$females[$i]] = $females[$i + 1];
            $pairedFemales[$females[$i + 1]] = $females[$i];
        }

        // First pass: greedily pair within same female-pair groups
        // Process in order so early males (low bye count) don't monopolize all females
        for ($i = 0; $i < count($indexed); $i++) {
            if ($indexed[$i]['paired']) continue;

            $p1 = $indexed[$i]['p'];
            $f1 = $p1['female'];
            $partnerFemale = $pairedFemales[$f1] ?? null;

            // Find compatible partner: same female-pair group, not yet paired
            $bestIdx = null;
            for ($j = $i + 1; $j < count($indexed); $j++) {
                if ($indexed[$j]['paired']) continue;
                $p2 = $indexed[$j]['p'];
                if ($p2['female'] !== $partnerFemale) continue;
                // Same male? can't pair
                if ($p1['male'] === $p2['male']) continue;
                $bestIdx = $j;
                break;
            }

            if ($bestIdx !== null) {
                $p2 = $indexed[$bestIdx]['p'];
                $indexed[$i]['paired'] = true;
                $indexed[$bestIdx]['paired'] = true;

                $match = [
                    'team1_players' => [$p1['male'], $p1['female']],
                    'team2_players' => [$p2['male'], $p2['female']],
                    'is_bye' => false,
                ];
                $roundMatches[] = $match;

                $realMatchesPerMale[$p1['male']]++;
                $realMatchesPerFemale[$p1['female']]++;
                $realMatchesPerMale[$p2['male']]++;
                $realMatchesPerFemale[$p2['female']]++;
            }
        }

        // Second pass: pair remaining partnerships (cross female groups, no overlap)
        for ($i = 0; $i < count($indexed); $i++) {
            if ($indexed[$i]['paired']) continue;

            $p1 = $indexed[$i]['p'];
            $p1Players = [$p1['male'], $p1['female']];
            $indexed[$i]['paired'] = true;

            $bestIdx = null;
            for ($j = $i + 1; $j < count($indexed); $j++) {
                if ($indexed[$j]['paired']) continue;
                $p2Players = [$indexed[$j]['p']['male'], $indexed[$j]['p']['female']];
                if (!empty(array_intersect($p1Players, $p2Players))) continue;
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
                // Partnership bye
                $key = self::partnershipKey($p1['male'], $p1['female']);
                $byeAllocator->recordBye($key, $p1Players);
                $maleByeCount[$p1['male']]++;

                $match = [
                    'team1_players' => $p1Players,
                    'team2_players' => [],
                    'is_bye' => true,
                    'bye_type' => 'partnership',
                ];
                $roundMatches[] = $match;
            }
        }

        // Assign bye slots to males NOT already in a real match this round.
        // This ensures ALL bye slots are utilized and all male-female partnerships appear.
        $realMaleIds = [];
        foreach ($roundMatches as $m) {
            if (empty($m['is_bye'])) {
                $realMaleIds = array_merge($realMaleIds, array_filter($m['team1_players'] ?? []));
                $realMaleIds = array_merge($realMaleIds, array_filter($m['team2_players'] ?? []));
            }
        }
        $realMaleIds = array_unique($realMaleIds);

        foreach ($byeSlots as $slot) {
            $maleId = (int) $slot['player_a'];
            if (in_array($maleId, $realMaleIds)) continue;
            $roundMatches[] = [
                'team1_players' => [$maleId, (int) $slot['player_b']],
                'team2_players' => [],
                'is_bye' => true,
                'bye_type' => 'partnership',
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
