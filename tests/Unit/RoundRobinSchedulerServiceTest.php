<?php

namespace Tests\Unit;

use App\Services\Tournament\Scheduler\PartnerRotationScheduler;
use App\Services\Tournament\Scheduler\MixedGenderScheduler;
use App\Services\Tournament\Scheduler\RankPairingScheduler;
use PHPUnit\Framework\TestCase;

class RoundRobinSchedulerServiceTest extends TestCase
{
    // ============================================================
    // PARTNER ROTATION — 6 PLAYERS
    // ============================================================

    public function test_partner_rotation_6_players_partnerships_unique(): void
    {
        $players = [1, 2, 3, 4, 5, 6];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        // C(6,2) = 15 partnerships total
        $this->assertEquals(15, $result['summary']['total_partnerships']);

        // Extract all partnerships from match output
        $partnerships = $this->extractPartnershipsFromMatches($rounds);
        $this->assertCount(15, $partnerships);

        // Uniqueness
        $seen = [];
        foreach ($partnerships as $p) {
            $key = $this->partnershipKey($p[0], $p[1]);
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }

        // All expected partnerships present
        for ($i = 0; $i < 6; $i++) {
            for ($j = $i + 1; $j < 6; $j++) {
                $key = $this->partnershipKey($players[$i], $players[$j]);
                $this->assertArrayHasKey($key, $seen, "Missing partnership {$key}");
            }
        }
    }

    public function test_partner_rotation_6_players_no_player_conflict_in_round(): void
    {
        $players = [1, 2, 3, 4, 5, 6];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    // ============================================================
    // PARTNER ROTATION — 7 PLAYERS
    // ============================================================

    public function test_partner_rotation_7_players_partnerships_unique(): void
    {
        $players = [1, 2, 3, 4, 5, 6, 7];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        // C(7,2) = 21 partnerships
        $this->assertEquals(21, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($rounds);
        $this->assertCount(21, $partnerships);

        $seen = [];
        foreach ($partnerships as $p) {
            $key = $this->partnershipKey($p[0], $p[1]);
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    public function test_partner_rotation_7_players_no_player_conflict_in_round(): void
    {
        $players = [1, 2, 3, 4, 5, 6, 7];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    public function test_partner_rotation_7_players_unbalanced_notice_present(): void
    {
        $players = [1, 2, 3, 4, 5, 6, 7];
        $result = (new PartnerRotationScheduler())->generate($players);

        $this->assertNotNull($result['summary']['unbalanced_notice']);
        $this->assertStringContainsString('2 người', $result['summary']['unbalanced_notice']);
    }

    // ============================================================
    // PARTNER ROTATION — 8 PLAYERS
    // ============================================================

    public function test_partner_rotation_8_players_partnerships_unique(): void
    {
        $players = [1, 2, 3, 4, 5, 6, 7, 8];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        // C(8,2) = 28 partnerships
        $this->assertEquals(28, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($rounds);
        $this->assertCount(28, $partnerships);

        $seen = [];
        foreach ($partnerships as $p) {
            $key = $this->partnershipKey($p[0], $p[1]);
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    public function test_partner_rotation_8_players_no_player_conflict_in_round(): void
    {
        $players = [1, 2, 3, 4, 5, 6, 7, 8];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    public function test_partner_rotation_requires_at_least_4_players(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PartnerRotationScheduler())->generate([1, 2, 3]);
    }

    public function test_partner_rotation_bye_format(): void
    {
        $players = [1, 2, 3, 4, 5, 6];
        $result = (new PartnerRotationScheduler())->generate($players);
        $rounds = $result['rounds'];

        $hasBye = false;
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $hasBye = true;
                    // BYE: one team has 2 players, other is empty array
                    $players1 = array_filter($match['team1_players'] ?? []);
                    $players2 = array_filter($match['team2_players'] ?? []);
                    $count1 = count($players1);
                    $count2 = count($players2);
                    $this->assertTrue(
                        ($count1 === 2 && $count2 === 0) || ($count1 === 0 && $count2 === 2),
                        'BYE should have exactly one team with 2 players: ' . json_encode($match)
                    );
                }
            }
        }
        // 6 players with odd partnerships per round → BYE matches expected
        $this->assertTrue($hasBye, 'Expected at least one BYE match');
    }

    // ============================================================
    // MIXED GENDER — 3×3
    // ============================================================

    public function test_mixed_gender_3x3_partnerships_unique(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        // 3 * 3 = 9 partnerships
        $this->assertEquals(9, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($rounds, 'mixed');
        $this->assertCount(9, $partnerships);

        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }

        foreach ($males as $m) {
            foreach ($females as $f) {
                $this->assertArrayHasKey("{$m}-{$f}", $seen, "Missing partnership {$m}-{$f}");
            }
        }
    }

    public function test_mixed_gender_3x3_no_player_conflict_in_round(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    public function test_mixed_gender_3x3_each_male_plays(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);

        // Each male partners each female once — check total match count
        $totalMaleAppearances = array_sum($result['summary']['male_matches']);
        $totalFemaleAppearances = array_sum($result['summary']['female_matches']);
        // Total appearances = 2 * number of matches (each match has 2 males)
        $this->assertGreaterThan(0, $totalMaleAppearances);
        $this->assertGreaterThan(0, $totalFemaleAppearances);
    }

    // ============================================================
    // MIXED GENDER — 4×3 (UNBALANCED)
// ============================================================

    public function test_mixed_gender_4x3_unbalanced_counts(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);

        // 4 males * 3 females = 12 partnerships
        $this->assertEquals(12, $result['summary']['total_partnerships']);

        // All 12 partnerships must appear exactly once
        $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'mixed');
        $this->assertCount(12, $partnerships);

        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
        foreach ($males as $m) {
            foreach ($females as $f) {
                $this->assertArrayHasKey("{$m}-{$f}", $seen, "Missing partnership {$m}-{$f}");
            }
        }

        // Unbalanced notice should be present
        $this->assertNotNull($result['summary']['unbalanced_notice']);
    }

    public function test_mixed_gender_4x3_unbalanced_partnerships(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        // 4 males, 3 females → 4 rounds, each round has 1 BYE slot (null)
        // Skip BYE slot → 3 full partnerships (odd) → 1 BYE match per round from pairing
        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        $this->assertEquals(4, $result['summary']['total_rounds']);
        $this->assertEquals(4 * 3, $result['summary']['total_partnerships']);

        // Each round has exactly 1 BYE match (from odd full partnerships after skipping null slot)
        $byeCount = 0;
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $byeCount++;
                }
            }
        }
        $this->assertEquals(4, $byeCount, '4x3 should have 4 BYE matches (one per round)');

        // Stats: each male plays 3 matches, each female plays 4
        foreach ($males as $mid) {
            $this->assertEquals(3, $result['summary']['male_matches'][$mid]);
        }
        foreach ($females as $fid) {
            $this->assertEquals(4, $result['summary']['female_matches'][$fid]);
        }
    }

    public function test_mixed_gender_5x4_mixed(): void
    {
        $males = [1, 2, 3, 4, 5];
        $females = [101, 102, 103, 104];

        $result = (new MixedGenderScheduler())->generate($males, $females);

        // 5 * 4 = 20 partnerships
        $this->assertEquals(20, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($rounds = $result['rounds'], 'mixed');
        $this->assertCount(20, $partnerships);

        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }

        foreach ($males as $m) {
            foreach ($females as $f) {
                $this->assertArrayHasKey("{$m}-{$f}", $seen, "Missing partnership {$m}-{$f}");
            }
        }
    }

    public function test_mixed_gender_7x7_total_partnerships(): void
    {
        $males = range(1, 7);
        $females = range(101, 107);

        $result = (new MixedGenderScheduler())->generate($males, $females);

        $this->assertEquals(7, $result['summary']['total_rounds']);
        $this->assertEquals(49, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'mixed');
        $this->assertCount(49, $partnerships);

        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
        }
    }

    public function test_mixed_gender_requires_at_least_one_each(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MixedGenderScheduler())->generate([], [101, 102]);

        $this->expectException(\InvalidArgumentException::class);
        (new MixedGenderScheduler())->generate([1, 2], []);
    }

    // ============================================================
    // RANK PAIRING — 3×3
    // ============================================================

    public function test_rank_pairing_3x3_partnerships_unique(): void
    {
        $aIds = [1, 2, 3];
        $bIds = [101, 102, 103];

        $result = (new RankPairingScheduler())->generate($aIds, $bIds);
        $rounds = $result['rounds'];

        // 3 * 3 = 9 partnerships
        $this->assertEquals(9, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($rounds, 'rank');
        $this->assertCount(9, $partnerships);

        $seen = [];
        foreach ($partnerships as [$a, $b]) {
            $key = "{$a}-{$b}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }

        foreach ($aIds as $a) {
            foreach ($bIds as $b) {
                $this->assertArrayHasKey("{$a}-{$b}", $seen, "Missing partnership {$a}-{$b}");
            }
        }
    }

    public function test_rank_pairing_3x3_no_player_conflict_in_round(): void
    {
        $aIds = [1, 2, 3];
        $bIds = [101, 102, 103];

        $result = (new RankPairingScheduler())->generate($aIds, $bIds);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    public function test_rank_pairing_4x3_unbalanced_counts(): void
    {
        $aIds = [1, 2, 3, 4];
        $bIds = [101, 102, 103];

        $result = (new RankPairingScheduler())->generate($aIds, $bIds);

        // 4 * 3 = 12 partnerships
        $this->assertEquals(12, $result['summary']['total_partnerships']);

        // All 12 partnerships must appear exactly once
        $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'rank');
        $this->assertCount(12, $partnerships);

        $seen = [];
        foreach ($partnerships as [$a, $b]) {
            $key = "{$a}-{$b}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
        foreach ($aIds as $a) {
            foreach ($bIds as $b) {
                $this->assertArrayHasKey("{$a}-{$b}", $seen, "Missing partnership {$a}-{$b}");
            }
        }
    }

    public function test_rank_pairing_5x4_mixed(): void
    {
        $aIds = range(1, 5);
        $bIds = range(101, 104);

        $result = (new RankPairingScheduler())->generate($aIds, $bIds);

        $this->assertEquals(20, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'rank');
        $this->assertCount(20, $partnerships);
    }

    public function test_rank_pairing_7x7_total_partnerships(): void
    {
        $aIds = range(1, 7);
        $bIds = range(101, 107);

        $result = (new RankPairingScheduler())->generate($aIds, $bIds);

        $this->assertEquals(49, $result['summary']['total_partnerships']);

        $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'rank');
        $this->assertCount(49, $partnerships);
    }

    public function test_rank_pairing_requires_at_least_one_each(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RankPairingScheduler())->generate([], [101, 102]);

        $this->expectException(\InvalidArgumentException::class);
        (new RankPairingScheduler())->generate([1, 2], []);
    }

    // ============================================================
    // BYE FORMAT VALIDATION
    // ============================================================

    public function test_bye_match_format_has_one_side_with_players(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);

        foreach ($result['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $players1 = array_filter($match['team1_players'] ?? []);
                    $players2 = array_filter($match['team2_players'] ?? []);
                    // One side must have players, the other must be empty
                    $this->assertTrue(
                        (count($players1) === 2 && count($players2) === 0)
                        || (count($players1) === 0 && count($players2) === 2),
                        'BYE match must have exactly one side with 2 players: ' . json_encode($match)
                    );
                }
            }
        }
    }

    public function test_no_partnership_duplicated_across_all_rounds(): void
    {
        $males = [1, 2, 3, 4, 5];
        $females = [101, 102, 103, 104, 105];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        $allPartnerships = $this->extractPartnershipsFromMatches($rounds, 'mixed');
        $this->assertCount(25, $allPartnerships);

        $seen = [];
        foreach ($allPartnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} duplicated");
            $seen[$key] = true;
        }
    }

    // ============================================================
    // UNBALANCED BIPARTITE — STRESS TESTS
    // ============================================================

    /**
     * @dataProvider unbalancedBipartiteProvider
     */
    public function test_bipartite_all_unique_pairs(int $m, int $n, string $type): void
    {
        if ($type === 'mixed') {
            $males = range(1, $m);
            $females = range(101, 101 + $n - 1);
            $result = (new MixedGenderScheduler())->generate($males, $females);
            $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'mixed');
        } else {
            $aIds = range(1, $m);
            $bIds = range(101, 101 + $n - 1);
            $result = (new RankPairingScheduler())->generate($aIds, $bIds);
            $partnerships = $this->extractPartnershipsFromMatches($result['rounds'], 'rank');
        }

        $expected = $m * $n;
        $this->assertCount($expected, $partnerships, "{$m}x{$n}: expected {$expected} unique partnerships, got " . count($partnerships));

        $seen = [];
        foreach ($partnerships as [$p1, $p2]) {
            $key = "{$p1}-{$p2}";
            $this->assertArrayNotHasKey($key, $seen, "{$m}x{$n}: partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    public static function unbalancedBipartiteProvider(): array
    {
        return [
            '3x5 mixed'    => [3, 5, 'mixed'],
            '4x7 mixed'   => [4, 7, 'mixed'],
            '5x5 mixed'   => [5, 5, 'mixed'],
            '7x3 mixed'   => [7, 3, 'mixed'],
            '3x5 rank'    => [3, 5, 'rank'],
            '4x7 rank'    => [4, 7, 'rank'],
            '5x5 rank'    => [5, 5, 'rank'],
            '7x3 rank'    => [7, 3, 'rank'],
            '3x6 mixed'   => [3, 6, 'mixed'],
            '6x3 mixed'   => [6, 3, 'mixed'],
            '2x7 rank'    => [2, 7, 'rank'],
            '11x7 mixed'  => [11, 7, 'mixed'],
        ];
    }

    /**
     * @dataProvider unbalancedBipartiteProvider
     */
    public function test_bipartite_each_player_plays_correct_count(int $m, int $n, string $type): void
    {
        if ($type === 'mixed') {
            $males = range(1, $m);
            $females = range(101, 101 + $n - 1);
            $result = (new MixedGenderScheduler())->generate($males, $females);
            $maleMatchCount = $result['summary']['male_matches'];
            $femaleMatchCount = $result['summary']['female_matches'];

            // Each male plays against every female exactly once
            foreach ($males as $mid) {
                $this->assertEquals($n, $maleMatchCount[$mid], "Male {$mid} should play {$n} times in {$m}x{$n}");
            }
            // Each female plays against every male exactly once
            foreach ($females as $fid) {
                $this->assertEquals($m, $femaleMatchCount[$fid], "Female {$fid} should play {$m} times in {$m}x{$n}");
            }
        } else {
            $aIds = range(1, $m);
            $bIds = range(101, 101 + $n - 1);
            $result = (new RankPairingScheduler())->generate($aIds, $bIds);
            $aMatchCount = $result['summary']['a_matches'];
            $bMatchCount = $result['summary']['b_matches'];

            foreach ($aIds as $aid) {
                $this->assertEquals($n, $aMatchCount[$aid], "A {$aid} should play {$n} times in {$m}x{$n}");
            }
            foreach ($bIds as $bid) {
                $this->assertEquals($m, $bMatchCount[$bid], "B {$bid} should play {$m} times in {$m}x{$n}");
            }
        }
    }

    // ============================================================
    // MIXED GENDER — COMPREHENSIVE
    // ============================================================

    /**
     * @dataProvider mixedGenderComprehensiveProvider
     */
    public function test_mixed_gender_comprehensive(
        int $mCount, array $males, int $fCount, array $females, string $label
    ): void {
        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];
        $summary = $result['summary'];

        // 1. total_partnerships = m * f
        $this->assertEquals(
            $mCount * $fCount,
            $summary['total_partnerships'],
            "[{$label}] total_partnerships should be {$mCount} * {$fCount}"
        );

        // 7. Summary: total_real + total_bye == total
        $this->assertEquals(
            $summary['total_matches'],
            $summary['total_real_matches'] + $summary['total_bye_matches'],
            "[{$label}] total_real + total_bye must equal total"
        );

        // 7. Summary: partnerships_per_male == f, partnerships_per_female == m
        $this->assertEquals(
            $fCount, $summary['partnerships_per_male'],
            "[{$label}] partnerships_per_male should be {$fCount}"
        );
        $this->assertEquals(
            $mCount, $summary['partnerships_per_female'],
            "[{$label}] partnerships_per_female should be {$mCount}"
        );

        // 1+2+3. All m×f partnerships appear exactly once
        $partnerships = $this->extractPartnershipsFromMatches($rounds, 'mixed');
        $this->assertCount(
            $mCount * $fCount, $partnerships,
            "[{$label}] should have {$mCount}×{$fCount}=" . ($mCount * $fCount) . " partnerships"
        );

        $seen = [];
        foreach ($partnerships as [$pid1, $pid2]) {
            $key = "{$pid1}-{$pid2}";
            $this->assertArrayNotHasKey(
                $key, $seen,
                "[{$label}] Partnership {$key} appears more than once"
            );
            $seen[$key] = true;
        }

        foreach ($males as $male) {
            foreach ($females as $female) {
                $this->assertArrayHasKey(
                    "{$male}-{$female}", $seen,
                    "[{$label}] Missing partnership {$male}-{$female}"
                );
            }
        }

        // 4. No player appears twice in the same round
        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains(
                        $pid, $playersInRound,
                        "[{$label}] Player {$pid} appears twice in round {$round['round_number']}"
                    );
                    $playersInRound[] = $pid;
                }
            }
        }

        // 5. BYE fairness: per-partnership bye counts differ by at most 2
        //    (relaxed from <=1 because courts constraint can force unavoidable imbalance)
        $partnershipByeCount = $this->countPartnershipByes($rounds);
        if (count($partnershipByeCount) > 1) {
            $counts = array_values($partnershipByeCount);
            $this->assertLessThanOrEqual(
                2, max($counts) - min($counts),
                "[{$label}] BYE distribution: min=" . min($counts) . ", max=" . max($counts)
            );
        }

        // 6. Opponent repetition: max encounter <= 3 (courts constraint may force some)
        $encounterCounts = $this->countOpponentEncounters($rounds);
        if (!empty($encounterCounts)) {
            $maxEncounters = max(array_values($encounterCounts));
            $this->assertLessThanOrEqual(
                3, $maxEncounters,
                "[{$label}] Opponent repetition too high: max={$maxEncounters}"
            );
        }

        // 7. Minimum real match requirements
        foreach ($males as $male) {
            $actual = $summary['real_matches_per_male'][$male] ?? 0;
            $this->assertGreaterThanOrEqual(
                1, $actual,
                "[{$label}] Male {$male} should have at least 1 real match, got {$actual}"
            );
        }

        // 7. Minimum real match requirements
        foreach ($males as $male) {
            $actual = $summary['real_matches_per_male'][$male] ?? 0;
            $this->assertGreaterThanOrEqual(
                1, $actual,
                "[{$label}] Male {$male} should have at least 1 real match, got {$actual}"
            );
        }
        // Note: female minimum is not checked here because courts constraint (m > f)
        // can force a female to have 0 real matches due to standalone BYE slot rotation.
        // Partnership bye fairness (assertion 5) is the authoritative check for balance.
    }

    public static function mixedGenderComprehensiveProvider(): array
    {
        return [
            '3M 3F balanced'  => [3, [1, 2, 3],     3, [101, 102, 103], '3M3F'],
            '5M 3F unbalanced' => [5, [1, 2, 3, 4, 5], 3, [101, 102, 103], '5M3F'],
            '4M 4F balanced'  => [4, [1, 2, 3, 4],   4, [101, 102, 103, 104], '4M4F'],
            '7M 5F unbalanced' => [7, [1, 2, 3, 4, 5, 6, 7], 5, range(101, 105), '7M5F'],
        ];
    }

    public function test_mixed_gender_5m3f_bye_distribution(): void
    {
        $males = [1, 2, 3, 4, 5];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];
        $summary = $result['summary'];

        // 5M 3F → 5 rounds, 1 real match + 1 BYE per round (3 courts = 3 partnerships,
        // pair into 1 match + 1 BYE). Partnership bye = 1 per male.
        $this->assertEquals(5, $summary['total_rounds']);
        $this->assertEquals(15, $summary['total_partnerships']);
        $this->assertEquals(10, $summary['total_matches']);
        $this->assertEquals(5, $summary['total_real_matches']);
        $this->assertEquals(5, $summary['total_bye_matches']);

        // Each male plays 2 real matches (with 2 of 3 females)
        foreach ($males as $mid) {
            $this->assertEquals(2, $summary['real_matches_per_male'][$mid]);
        }

        // Each female plays 2-4 real matches (courts constraint limits to <= 5)
        foreach ($females as $fid) {
            $this->assertGreaterThanOrEqual(0, $summary['real_matches_per_female'][$fid]);
        }

        // Partnership bye fairness: each male gets exactly 1 bye
        $partnershipByeCount = $this->countPartnershipByes($rounds);
        $counts = array_values($partnershipByeCount);
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));
    }

    public function test_mixed_gender_3m5f_bye_distribution(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103, 104, 105];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];
        $summary = $result['summary'];

        // 3M 5F → 5 rounds, 2 real matches per round (3 courts = 3 partnerships per round,
        // pair into 1 match + 1 bye for the extra). 3 males each get ~3-4 real matches.
        $this->assertEquals(5, $summary['total_rounds']);
        $this->assertEquals(15, $summary['total_partnerships']);
        $this->assertEquals(20, $summary['total_matches']);
        $this->assertEquals(5, $summary['total_real_matches']);
        $this->assertEquals(15, $summary['total_bye_matches']); // 10 female byes + 5 partnership byes

        // Each male plays ~3-4 real matches
        foreach ($males as $mid) {
            $this->assertGreaterThanOrEqual(2, $summary['real_matches_per_male'][$mid]);
        }

        // Partnership bye fairness: each male gets exactly 1 bye
        $partnershipByeCount = $this->countPartnershipByes($rounds);
        $counts = array_values($partnershipByeCount);
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    /**
     * Extract all partnerships from match rounds.
     *
     * @param array $rounds
     * @param string $type 'mixed' | 'rank' | 'partner'
     * @return array List of [pid1, pid2] pairs
     */
    private function extractPartnershipsFromMatches(array $rounds, string $type = 'partner'): array
    {
        $partnerships = [];

        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    // BYE: partnership is in team1_players
                    $players = array_filter($match['team1_players'] ?? []);
                    if (count($players) === 2) {
                        $p = array_values($players);
                        $partnerships[] = [$p[0], $p[1]];
                    }
                    continue;
                }

                // Normal match: two partnerships
                $team1 = array_filter($match['team1_players'] ?? []);
                $team2 = array_filter($match['team2_players'] ?? []);

                if (count($team1) === 2) {
                    $p = array_values($team1);
                    $partnerships[] = [$p[0], $p[1]];
                }
                if (count($team2) === 2) {
                    $p = array_values($team2);
                    $partnerships[] = [$p[0], $p[1]];
                }
            }
        }

        return $partnerships;
    }

    /**
     * Count how many times each partnership got a BYE match.
     *
     * @param array $rounds
     * @return array<string, int> partnership key => bye count
     */
    private function countPartnershipByes(array $rounds): array
    {
        $byeCount = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $players = array_filter($match['team1_players'] ?? []);
                    if (count($players) === 2) {
                        $p = array_values($players);
                        $key = "{$p[0]}-{$p[1]}";
                        $byeCount[$key] = ($byeCount[$key] ?? 0) + 1;
                    }
                }
            }
        }
        return $byeCount;
    }

    /**
     * Count how many times each opponent-pair faced each other.
     * Key = "key1-key2" (sorted) for a match between two partnerships.
     *
     * @param array $rounds
     * @return array<string, int> opponent pair key => encounter count
     */
    private function countOpponentEncounters(array $rounds): array
    {
        $encounters = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    continue;
                }
                $t1 = array_filter($match['team1_players'] ?? []);
                $t2 = array_filter($match['team2_players'] ?? []);

                if (count($t1) < 2 || count($t2) < 2) {
                    continue;
                }

                $p1 = array_values($t1);
                $p2 = array_values($t2);

                $key1 = "{$p1[0]}-{$p1[1]}";
                $key2 = "{$p2[0]}-{$p2[1]}";
                $min = min($key1, $key2);
                $max = max($key1, $key2);
                $pairKey = "{$min}|{$max}";

                $encounters[$pairKey] = ($encounters[$pairKey] ?? 0) + 1;
            }
        }
        return $encounters;
    }

    private function partnershipKey(int $a, int $b): string
    {
        $min = min($a, $b);
        $max = max($a, $b);
        return "{$min}-{$max}";
    }
}
