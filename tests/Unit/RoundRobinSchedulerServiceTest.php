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

    public function test_mixed_gender_4x3_bye_handled(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        $result = (new MixedGenderScheduler())->generate($males, $females);
        $rounds = $result['rounds'];

        // With 4 males and 3 females, some rounds will have odd partnerships → BYE
        $hasBye = false;
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $hasBye = true;
                    // BYE format: one side has 2 players, other is empty array
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
        $this->assertTrue($hasBye, 'Expected BYE with unbalanced groups');
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

    private function partnershipKey(int $a, int $b): string
    {
        $min = min($a, $b);
        $max = max($a, $b);
        return "{$min}-{$max}";
    }
}
