<?php

namespace Tests\Unit;

use App\Services\RoundRobinSchedulerService;
use PHPUnit\Framework\TestCase;

class RoundRobinSchedulerServiceTest extends TestCase
{
    private RoundRobinSchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new RoundRobinSchedulerService();
    }

    // ============================================================
    // MIXED GENDER — SINGLE FORMAT
    // ============================================================

    public function test_mixed_gender_single_3x3_partnerships_unique(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'single', false);
        $rounds = $result['rounds'];

        $this->assertCount(3, $rounds);

        // Collect all partnerships
        $partnerships = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                $partnerships[] = [$match['participant1_id'], $match['participant2_id']];
            }
        }

        // Total: 3 * 3 = 9 partnerships
        $this->assertCount(9, $partnerships);

        // Each male-female pair must appear exactly once
        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }

        // Every combination present
        foreach ($males as $m) {
            foreach ($females as $f) {
                $this->assertArrayHasKey("{$m}-{$f}", $seen, "Missing partnership {$m}-{$f}");
            }
        }
    }

    public function test_mixed_gender_single_no_player_conflict_in_round(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103, 104];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'single', false);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                foreach ([$match['participant1_id'], $match['participant2_id']] as $pid) {
                    $this->assertNotContains($pid, $playersInRound,
                        "Player {$pid} appears twice in round {$round['round_number']}");
                    $playersInRound[] = $pid;
                }
            }
        }
    }

    public function test_mixed_gender_single_4x3_unbalanced_counts(): void
    {
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'single', false);

        $this->assertEquals(4, $result['summary']['total_rounds']);
        $this->assertEquals(12, $result['summary']['total_matches']);

        // Each male plays 3 times (one per female)
        foreach ($males as $m) {
            $this->assertEquals(3, $result['summary']['male_matches'][$m],
                "Male {$m} should play 3 times");
        }

        // Each female plays 4 times (one per male)
        foreach ($females as $f) {
            $this->assertEquals(4, $result['summary']['female_matches'][$f],
                "Female {$f} should play 4 times");
        }
    }

    public function test_mixed_gender_single_7x7_total_partnerships(): void
    {
        $males = range(1, 7);
        $females = range(101, 107);

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'single', false);

        $this->assertEquals(7, $result['summary']['total_rounds']);
        $this->assertEquals(49, $result['summary']['total_matches']);

        // Each partnership unique
        $partnerships = [];
        foreach ($result['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $partnerships[] = [$match['participant1_id'], $match['participant2_id']];
            }
        }
        $this->assertCount(49, $partnerships);
    }

    // ============================================================
    // MIXED GENDER — DOUBLE FORMAT
    // ============================================================

    public function test_mixed_gender_double_3x3_each_partnership_appears_once(): void
    {
        $males = [1, 2, 3];
        $females = [101, 102, 103];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'double', false);
        $rounds = $result['rounds'];

        $this->assertCount(3, $rounds);

        // Collect all (male, female) pairs from team1 and team2 across all matches
        $partnerships = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                foreach (['team1_players', 'team2_players'] as $key) {
                    $players = array_filter($match[$key] ?? []);
                    if (count($players) === 2) {
                        $males_in_team = array_values(array_intersect($players, $males));
                        $females_in_team = array_values(array_intersect($players, $females));
                        if (count($males_in_team) === 1 && count($females_in_team) === 1) {
                            $partnerships[] = [$males_in_team[0], $females_in_team[0]];
                        }
                    }
                }
            }
        }

        // Total: 3 * 3 = 9 partnerships
        $this->assertCount(9, $partnerships, 'Every partnership should appear exactly once in double format');

        // Uniqueness check
        $seen = [];
        foreach ($partnerships as [$m, $f]) {
            $key = "{$m}-{$f}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    public function test_mixed_gender_double_no_player_conflict_in_round(): void
    {
        $males = [1, 2, 3, 4, 5];
        $females = [101, 102, 103, 104, 105];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'double', false);
        $rounds = $result['rounds'];

        foreach ($rounds as $round) {
            $playersInRound = [];
            foreach ($round['matches'] as $match) {
                $playersInRound = array_merge($playersInRound, array_filter($match['team1_players'] ?? []));
                $playersInRound = array_merge($playersInRound, array_filter($match['team2_players'] ?? []));
            }
            $unique = array_unique($playersInRound);
            $this->assertCount(count($playersInRound), $unique,
                "Duplicate players in round {$round['round_number']}: " . json_encode($playersInRound));
        }
    }

    public function test_mixed_gender_double_odd_bye_handling(): void
    {
        // 3 males + 3 females = 3 per round. Even → no BYE in perfect rounds.
        // But 4 males + 3 females = max 3 per round. Some rounds will have BYE.
        $males = [1, 2, 3, 4];
        $females = [101, 102, 103];

        $result = $this->scheduler->generateMixedGenderSchedule($males, $females, 'double', false);
        $rounds = $result['rounds'];

        // At least one round should have a BYE (odd number of partnerships in a round)
        $hasBye = false;
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                if (!empty($match['is_bye'])) {
                    $hasBye = true;
                    // BYE match should have one side with players
                    $players1 = array_filter($match['team1_players'] ?? []);
                    $players2 = array_filter($match['team2_players'] ?? []);
                    $this->assertNotEmpty($players1 xor $players2,
                        'BYE match should have exactly one side with players');
                }
            }
        }
        $this->assertTrue($hasBye, 'Expected at least one BYE match with uneven groups');
    }

    // ============================================================
    // RANK PAIRING — SINGLE FORMAT
    // ============================================================

    public function test_rank_pairing_single_3x3_partnerships_unique(): void
    {
        $aIds = [1, 2, 3];
        $bIds = [101, 102, 103];

        $result = $this->scheduler->generateRankPairingSchedule($aIds, $bIds, 'single', false);
        $rounds = $result['rounds'];

        $this->assertCount(3, $rounds);

        $partnerships = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                $partnerships[] = [$match['participant1_id'], $match['participant2_id']];
            }
        }

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

    public function test_rank_pairing_single_5x4_counts(): void
    {
        $aIds = range(1, 5);
        $bIds = range(101, 104);

        $result = $this->scheduler->generateRankPairingSchedule($aIds, $bIds, 'single', false);

        $this->assertEquals(5, $result['summary']['total_rounds']);
        $this->assertEquals(20, $result['summary']['total_matches']);

        // Each A plays 4 times (one per B)
        foreach ($aIds as $a) {
            $this->assertEquals(4, $result['summary']['a_matches'][$a]);
        }

        // Each B plays 5 times (one per A)
        foreach ($bIds as $b) {
            $this->assertEquals(5, $result['summary']['b_matches'][$b]);
        }
    }

    // ============================================================
    // RANK PAIRING — DOUBLE FORMAT
    // ============================================================

    public function test_rank_pairing_double_3x3_each_partnership_once(): void
    {
        $aIds = [1, 2, 3];
        $bIds = [101, 102, 103];

        $result = $this->scheduler->generateRankPairingSchedule($aIds, $bIds, 'double', false);
        $rounds = $result['rounds'];

        $this->assertCount(3, $rounds);

        $partnerships = [];
        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                foreach (['team1_players', 'team2_players'] as $key) {
                    $players = array_filter($match[$key] ?? []);
                    if (count($players) === 2) {
                        $a_in_team = array_values(array_intersect($players, $aIds));
                        $b_in_team = array_values(array_intersect($players, $bIds));
                        if (count($a_in_team) === 1 && count($b_in_team) === 1) {
                            $partnerships[] = [$a_in_team[0], $b_in_team[0]];
                        }
                    }
                }
            }
        }

        $this->assertCount(9, $partnerships, 'Every partnership should appear exactly once in double format');

        $seen = [];
        foreach ($partnerships as [$a, $b]) {
            $key = "{$a}-{$b}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    public function test_rank_pairing_double_7x7_total_partnerships(): void
    {
        $aIds = range(1, 7);
        $bIds = range(101, 107);

        $result = $this->scheduler->generateRankPairingSchedule($aIds, $bIds, 'double', false);

        $this->assertEquals(7, $result['summary']['total_rounds']);

        // In double format, total_matches = match objects (not partnerships).
        // 7 rounds × floor(7/2) = 24 matches + 7 BYEs = 31 rows.
        // Partnerships are all 49 a×b pairs, each appearing once.
        $partnerships = [];
        foreach ($result['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                foreach (['team1_players', 'team2_players'] as $key) {
                    $players = array_filter($match[$key] ?? []);
                    if (count($players) === 2) {
                        $a_in = array_values(array_intersect($players, $aIds));
                        $b_in = array_values(array_intersect($players, $bIds));
                        if (count($a_in) === 1 && count($b_in) === 1) {
                            $partnerships[] = [$a_in[0], $b_in[0]];
                        }
                    }
                }
            }
        }

        $this->assertCount(49, $partnerships, 'Every a×b partnership must appear exactly once');

        $seen = [];
        foreach ($partnerships as [$a, $b]) {
            $key = "{$a}-{$b}";
            $this->assertArrayNotHasKey($key, $seen, "Partnership {$key} appears more than once");
            $seen[$key] = true;
        }
    }

    // ============================================================
    // BOUNDARY CASES
    // ============================================================

    public function test_mixed_gender_requires_at_least_one_each(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scheduler->generateMixedGenderSchedule([], [101, 102, 103]);
    }

    public function test_mixed_gender_single_1x1(): void
    {
        $result = $this->scheduler->generateMixedGenderSchedule([1], [101], 'single', false);

        $this->assertCount(1, $result['rounds']);
        $this->assertEquals(1, $result['summary']['total_matches']);

        $match = $result['rounds'][0]['matches'][0];
        $this->assertEquals(1, $match['participant1_id']);
        $this->assertEquals(101, $match['participant2_id']);
        $this->assertFalse($match['is_bye']);
    }

    public function test_rank_pairing_requires_at_least_one_each(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scheduler->generateRankPairingSchedule([], [101, 102]);
    }
}
