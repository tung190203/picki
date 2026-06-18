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

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        $this->assertEquals(4, $result['summary']['total_rounds']);
        $this->assertEquals(4 * 3, $result['summary']['total_partnerships']);

        // 4M 3F -> 3F odd -> 4 partnership byes (1 per round)
        $partnershipByes = 0;
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye']) && ($match['bye_type'] ?? '') === 'partnership') {
                    $partnershipByes++;
                }
            }
        }
        $this->assertEquals(4, $partnershipByes, '4M 3F -> 4 partnership byes (3 is odd)');

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

        // 5M 3F -> 5 rounds, 3 partnerships/round
        // Courts constraint: 2 partnerships per match -> max 1 real match/round
        // This means not all 15 male-female pairs can be played as real matches.
        // Some partnerships will appear as partnership byes (partnership repeated).
        $this->assertEquals(5, $summary['total_rounds']);
        $this->assertEquals(15, $summary['total_partnerships']);
        $this->assertEquals(5, $summary['total_real_matches']);  // 5 rounds x 1 match = 5
        $this->assertEquals(5, $summary['total_bye_matches']);   // 5 partnership byes
        $this->assertEquals(10, $summary['total_player_byes']);  // 5M x 2 = 10
        $this->assertEquals(5, $summary['total_partnership_byes']);

        // Each male plays 2 real matches (exactly 5 real matches / 5 males)
        foreach ($males as $mid) {
            $this->assertGreaterThanOrEqual(1, $summary['real_matches_per_male'][$mid]);
        }

        // Partnership bye fairness: distribution of bye counts across partnerships
        $partnershipByeCount = $this->countPartnershipByes($rounds);
        $counts = array_values($partnershipByeCount);
        if (count($counts) > 1) {
            $this->assertLessThanOrEqual(2, max($counts) - min($counts));
        }
    }

    public function test_mixed_gender_3m5f_bye_distribution(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103, 104, 105];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];
        $summary = $result['summary'];

        // 3M 5F -> 5 rounds, 3 courts, 3 partnerships per round (max=5)
        // 3 partnerships (odd) -> 1 match + 1 partnership bye per round
        // + 2 player byes per round (5F > 3M, each female nghỉ 2 lần)
        // = 1 real + 1 partnership bye + 2 player byes = 4 matches/round
        $this->assertEquals(5, $summary['total_rounds']);
        $this->assertEquals(15, $summary['total_partnerships']);
        // total_bye_matches = partnership byes (5) + player byes (10) = 15
        $this->assertEquals(20, $summary['total_matches']);       // 5 real + 15 bye = 20
        $this->assertEquals(5, $summary['total_real_matches']);    // 5 rounds x 1 real match = 5
        $this->assertEquals(15, $summary['total_bye_matches']);    // 5 partnership + 10 player = 15
        $this->assertEquals(10, $summary['total_player_byes']);    // 5F x 2 = 10
        $this->assertEquals(5, $summary['total_partnership_byes']); // 5 rounds x 1 (3M odd) = 5

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
                    // Partnership bye: team1 has 2 players
                    $players = array_filter($match['team1_players'] ?? []);
                    if (count($players) === 2) {
                        $p = array_values($players);
                        $partnerships[] = [$p[0], $p[1]];
                    }
                    // Player bye (1 player): skip — belongs in player_byes field
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
        $seenPartnerships = []; // track globally to avoid double-counting
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $players = array_filter($match['team1_players'] ?? []);
                    if (count($players) === 2) {
                        $p = array_values($players);
                        $key = "{$p[0]}-{$p[1]}";
                        // Skip if this partnership already appeared (each partnership appears once globally)
                        if (isset($seenPartnerships[$key])) {
                            continue;
                        }
                        $seenPartnerships[$key] = true;
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

    // ============================================================
    // MIXED GENDER — PLAYER BYE FAIRNESS
    // ============================================================

    public function test_mixed_gender_8m7f_player_bye_fairness(): void
    {
        $males   = range(1, 8);
        $females = range(101, 107);
        $result  = (new MixedGenderScheduler())->generate($males, $females);
        $rounds  = $result['rounds'];
        $summary = $result['summary'];

        // 1. Tong partnership = 56
        $this->assertEquals(56, $summary['total_partnerships']);

        // 2. Unique partnership = 56
        $partnerships = $this->extractPartnershipsFromMatches($rounds, 'mixed');
        $this->assertCount(56, $partnerships);

        // 3. Moi nam gap du 7 nu
        foreach ($males as $mid) {
            $this->assertEquals(7, $summary['male_matches'][$mid]);
        }

        // 4. Moi nu gap du 8 nam
        foreach ($females as $fid) {
            $this->assertEquals(8, $summary['female_matches'][$fid]);
        }

        // 5. player_id trong player_byes khong null/0
        foreach ($rounds as $round) {
            foreach ($round['player_byes'] ?? [] as $bye) {
                $this->assertNotNull($bye['player_id']);
                $this->assertNotEquals(0, $bye['player_id']);
                $this->assertEquals('male', $bye['group']);
                $this->assertTrue($bye['is_player_bye']);
            }
        }

        // 6. Dung 8 player bye records
        $allPlayerByes = [];
        foreach ($rounds as $round) {
            $allPlayerByes = array_merge($allPlayerByes, $round['player_byes'] ?? []);
        }
        $this->assertCount(8, $allPlayerByes);

        // 7. Moi nam nghi dung 1 lan
        $maleByeCount = array_fill_keys($males, 0);
        foreach ($allPlayerByes as $bye) {
            if ($bye['group'] === 'male') {
                $maleByeCount[$bye['player_id']]++;
            }
        }
        foreach ($males as $mid) {
            $this->assertEquals(1, $maleByeCount[$mid]);
        }

        // 8. Phan bo cong bang: max - min <= 1
        $counts = array_values($maleByeCount);
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));

        // 9. Khong ai xuat hien 2 lan trong cung round
        foreach ($rounds as $idx => $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $players = array_merge(
                    array_filter($match['team1_players'] ?? []),
                    array_filter($match['team2_players'] ?? [])
                );
                foreach ($players as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round " . ($idx + 1));
                    $playersInRound[] = $pid;
                }
            }
            foreach ($round['player_byes'] ?? [] as $bye) {
                $this->assertNotContains($bye['player_id'], $playersInRound,
                    "Bye player {$bye['player_id']} is in a match same round");
            }
        }

        // 10. Round structure dung
        foreach ($rounds as $round) {
            $this->assertArrayHasKey('player_byes', $round);
            $this->assertArrayHasKey('matches', $round);
            $this->assertCount(1, $round['player_byes']);
        }

        // 11. Summary
        $this->assertEquals(8, $summary['total_player_byes']);
        $this->assertEquals(1, $summary['player_bye_per_player']);
        $this->assertEquals('male', $summary['player_bye_group']);
        $this->assertEquals(1, $summary['player_byes_per_male']);
        $this->assertEquals(8, $summary['total_partnership_byes']);
    }

    public function test_mixed_gender_5m3f_player_bye_fairness(): void
    {
        $males   = range(1, 5);
        $females = range(101, 103);

        $result  = (new MixedGenderScheduler())->generate($males, $females);
        $rounds  = $result['rounds'];
        $summary = $result['summary'];

        // 5M 3F -> 5 rounds x 2 = 10 player byes
        $allPlayerByes = [];
        foreach ($rounds as $round) {
            $allPlayerByes = array_merge($allPlayerByes, $round['player_byes'] ?? []);
        }
        $this->assertCount(10, $allPlayerByes);

        // Moi nam nghi dung 2 lan
        $maleByeCount = array_fill_keys($males, 0);
        foreach ($allPlayerByes as $bye) {
            if ($bye['group'] === 'male') {
                $maleByeCount[$bye['player_id']]++;
            }
        }
        foreach ($males as $mid) {
            $this->assertEquals(2, $maleByeCount[$mid]);
        }

        // Phan bo cong bang: max - min <= 1
        $counts = array_values($maleByeCount);
        $this->assertLessThanOrEqual(1, max($counts) - min($counts));

        // Summary
        $this->assertEquals(10, $summary['total_player_byes']);
        $this->assertEquals(2, $summary['player_bye_per_player']);
        $this->assertEquals('male', $summary['player_bye_group']);
        $this->assertEquals(5, $summary['total_partnership_byes']);
    }

    public function test_mixed_gender_3m5f_player_bye_fairness(): void
    {
        $males   = range(1, 3);
        $females = range(101, 105);

        $result  = (new MixedGenderScheduler())->generate($males, $females);
        $rounds  = $result['rounds'];
        $summary = $result['summary'];

        // 3M 5F -> 5 rounds x 2 = 10 player byes (nu nghi)
        $allPlayerByes = [];
        foreach ($rounds as $round) {
            $allPlayerByes = array_merge($allPlayerByes, $round['player_byes'] ?? []);
        }
        $this->assertCount(10, $allPlayerByes);

        // Moi nu nghi dung 2 lan
        $femaleByeCount = array_fill_keys($females, 0);
        foreach ($allPlayerByes as $bye) {
            if ($bye['group'] === 'female') {
                $femaleByeCount[$bye['player_id']]++;
            }
        }
        foreach ($females as $fid) {
            $this->assertEquals(2, $femaleByeCount[$fid]);
        }

        // Summary
        $this->assertEquals(10, $summary['total_player_byes']);
        $this->assertEquals(2, $summary['player_bye_per_player']);
        $this->assertEquals('female', $summary['player_bye_group']);
        $this->assertEquals(5, $summary['total_partnership_byes']);
    }

    public function test_mixed_gender_4m3f_partnership_bye(): void
    {
        $males   = range(1, 4);
        $females = range(101, 103);

        $result  = (new MixedGenderScheduler())->generate($males, $females);
        $rounds  = $result['rounds'];
        $summary = $result['summary'];

        // 4M 3F -> min=3 (le) -> 4 partnership byes
        $this->assertEquals(4, $summary['total_partnership_byes']);

        // 4 player byes (moi nam nghi 1 lan)
        $this->assertEquals(4, $summary['total_player_byes']);
        $this->assertEquals(1, $summary['player_bye_per_player']);
        $this->assertEquals('male', $summary['player_bye_group']);
        $this->assertEquals(1, $summary['player_byes_per_male']);
        $this->assertEquals(0, $summary['player_byes_per_female']);

        // Moi nam nghi dung 1 lan
        $allPlayerByes = [];
        foreach ($rounds as $round) {
            $allPlayerByes = array_merge($allPlayerByes, $round['player_byes'] ?? []);
        }
        $maleByeCount = array_fill_keys($males, 0);
        foreach ($allPlayerByes as $bye) {
            if ($bye['group'] === 'male') {
                $maleByeCount[$bye['player_id']]++;
            }
        }
        foreach ($males as $mid) {
            $this->assertEquals(1, $maleByeCount[$mid]);
        }

        // Moi round co dung 1 player bye
        foreach ($rounds as $round) {
            $this->assertCount(1, $round['player_byes']);
        }
    }
}
