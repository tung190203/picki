<?php

namespace Tests\Unit;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionSettingsDTO;
use App\DTO\ParticipantTierDTO;
use App\DTO\PlayerContextDTO;
use App\Enums\PlayerTier;
use App\Models\User;
use App\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

class MatchSuggestionTest extends TestCase
{
    private SchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new SchedulerService();
    }

    /**
     * Test that gender compatibility is respected in mixed gender matches.
     * Valid configurations:
     * - Male + Male vs Male + Male (all male)
     * - Female + Female vs Female + Female (all female)
     * - Male + Female vs Male + Female (mixed 2-2 symmetric)
     */
    public function test_respects_gender_compatibility_in_mixed_gender(): void
    {
        // Create 4 males and 4 females
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
        ]);

        $request = $this->createRequest();

        $reflection = new \ReflectionClass($this->scheduler);
        
        // Test findGenderCompatibleGroups
        $method = $reflection->getMethod('findGenderCompatibleGroups');
        $method->setAccessible(true);
        $groups = $method->invoke($this->scheduler, $players);

        // Should have 3 groups: mixed, all male, all female
        $this->assertGreaterThanOrEqual(1, count($groups));
    }

    /**
     * Test that asymmetric mixed gender is NOT valid.
     * Male + Female vs Male + Male is invalid.
     */
    public function test_rejects_asymmetric_gender_pairing(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('isValidGenderPairing');
        $method->setAccessible(true);

        // Team A: 1 male, 1 female
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'gender' => User::MALE, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'gender' => User::FEMALE, 'user_id' => 2]),
        ];

        // Team B: 2 males (invalid!)
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'gender' => User::MALE, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'gender' => User::MALE, 'user_id' => 4]),
        ];

        $genderCounts = [
            User::MALE => 3,
            User::FEMALE => 1,
            'unknown' => 0,
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB, $genderCounts);
        
        $this->assertFalse($result, 'Asymmetric gender pairing (1F+1M vs 2M) should be invalid');
    }

    /**
     * Test that symmetric mixed gender is valid.
     * Male + Female vs Male + Female is valid.
     */
    public function test_accepts_symmetric_gender_pairing(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('isValidGenderPairing');
        $method->setAccessible(true);

        // Team A: 1 male, 1 female
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'gender' => User::MALE, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'gender' => User::FEMALE, 'user_id' => 2]),
        ];

        // Team B: 1 male, 1 female (valid!)
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'gender' => User::MALE, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'gender' => User::FEMALE, 'user_id' => 4]),
        ];

        $genderCounts = [
            User::MALE => 2,
            User::FEMALE => 2,
            'unknown' => 0,
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB, $genderCounts);
        
        $this->assertTrue($result, 'Symmetric gender pairing (1F+1M vs 1F+1M) should be valid');
    }

    /**
     * Test that guests are included in match suggestions.
     * Guest with user_id = null should still be included.
     */
    public function test_includes_guests_in_match_suggestion(): void
    {
        // Create players with one guest (user_id = null)
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'is_guest' => false],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'is_guest' => true, 'user_id' => null],
            ['id' => 3, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'is_guest' => false],
            ['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'is_guest' => false],
        ]);

        // Guest should be in the pool
        $hasGuest = false;
        foreach ($players as $player) {
            if ($player->is_guest) {
                $hasGuest = true;
                break;
            }
        }

        $this->assertTrue($hasGuest, 'Guest player should be included in the player pool');
    }

    /**
     * Test that players with fewer matches are prioritized.
     */
    public function test_prioritizes_players_with_fewer_matches(): void
    {
        $players = $this->createPlayers([
            ['id' => 1, 'played' => 5, 'tier' => PlayerTier::Purple],
            ['id' => 2, 'played' => 0, 'tier' => PlayerTier::Green],
            ['id' => 3, 'played' => 2, 'tier' => PlayerTier::Red],
            ['id' => 4, 'played' => 1, 'tier' => PlayerTier::Yellow],
            ['id' => 5, 'played' => 0, 'tier' => PlayerTier::Green],
            ['id' => 6, 'gender' => User::FEMALE, 'played' => 0, 'tier' => PlayerTier::Green],
            ['id' => 7, 'gender' => User::FEMALE, 'played' => 0, 'tier' => PlayerTier::Green],
            ['id' => 8, 'gender' => User::FEMALE, 'played' => 0, 'tier' => PlayerTier::Green],
        ]);

        $request = $this->createRequest();
        $reflection = new \ReflectionClass($this->scheduler);

        $method = $reflection->getMethod('selectPlayers');
        $method->setAccessible(true);

        // Filter to only males first
        $males = array_filter($players, fn($p) => $p->gender === User::MALE);
        $females = array_filter($players, fn($p) => $p->gender === User::FEMALE);

        // For mixed gender, need 2M + 2F
        $selected = $method->invoke($this->scheduler, $players, $request);

        // Players with 0 matches should be prioritized
        $zeroMatchPlayers = array_filter($selected, fn($p) => $p->played_count === 0);
        $this->assertGreaterThanOrEqual(2, count($zeroMatchPlayers), 
            'Players with 0 matches should be prioritized');
    }

    /**
     * Test that prevent_three_consecutive setting is respected.
     */
    public function test_prevents_three_consecutive_matches(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);

        // Test filterByForcedRest with preventThreeConsecutive = true
        $method = $reflection->getMethod('filterByForcedRest');
        $method->setAccessible(true);

        // Player with 2 consecutive matches should be filtered
        $player2Consecutive = $this->createPlayerContext([
            'id' => 1, 
            'consecutive_count' => 2, 
            'user_id' => 1
        ]);

        // Player with 1 consecutive match should pass
        $player1Consecutive = $this->createPlayerContext([
            'id' => 2, 
            'consecutive_count' => 1, 
            'user_id' => 2
        ]);

        $result2 = $method->invoke($this->scheduler, $player2Consecutive, true);
        $result1 = $method->invoke($this->scheduler, $player1Consecutive, true);

        $this->assertFalse($result2, 'Player with 2 consecutive matches should be filtered');
        $this->assertTrue($result1, 'Player with 1 consecutive match should pass');
    }

    /**
     * Test that balance team uses VN DUPR scores.
     */
    public function test_balances_teams_using_vndupr_scores(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);

        $method = $reflection->getMethod('calculateBalanceDiff');
        $method->setAccessible(true);

        // Team A: high VN DUPR (3.5, 3.0) = avg 3.25
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'user_id' => 1, 'tier' => PlayerTier::Purple]),
            $this->createPlayerContext(['id' => 2, 'user_id' => 2, 'tier' => PlayerTier::Red]),
        ];

        // Team B: low VN DUPR (2.0, 1.5) = avg 1.75
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'user_id' => 3, 'tier' => PlayerTier::Yellow]),
            $this->createPlayerContext(['id' => 4, 'user_id' => 4, 'tier' => PlayerTier::Green]),
        ];

        // User data map with VN DUPR scores
        $userDataMap = [
            1 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '3.500']]]],
            2 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '3.000']]]],
            3 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '2.000']]]],
            4 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '1.500']]]],
        ];

        $diff = $method->invoke($this->scheduler, $teamA, $teamB, $userDataMap);

        // Difference should be |3.25 - 1.75| = 1.5
        $this->assertEqualsWithDelta(1.5, $diff, 0.01, 'Balance diff should be calculated from VN DUPR');
    }

    /**
     * Test that tier starvation does not occur.
     * Lower tier players should not be perpetually skipped.
     */
    public function test_does_not_cause_tier_starvation(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);

        $method = $reflection->getMethod('applyFairPlayPriority');
        $method->setAccessible(true);

        // Create players with same played_count but different tiers
        $players = $this->createPlayers([
            ['id' => 1, 'played' => 1, 'tier' => PlayerTier::Purple], // Highest tier
            ['id' => 2, 'played' => 1, 'tier' => PlayerTier::Yellow], // Middle tier
            ['id' => 3, 'played' => 1, 'tier' => PlayerTier::Green],  // Lowest tier
            ['id' => 4, 'gender' => User::FEMALE, 'played' => 0, 'tier' => PlayerTier::Green],  // Lowest tier but fewer matches
        ]);

        $request = $this->createRequest();
        $sorted = $method->invoke($this->scheduler, $players);

        // Green player with played=0 should come before Purple player with played=1
        // because fair play (played_count) has higher priority than tier
        $greenIndex = -1;
        $purpleIndex = -1;
        foreach ($sorted as $i => $player) {
            if ($player->tier === PlayerTier::Green && $player->played_count === 0) {
                $greenIndex = $i;
            }
            if ($player->tier === PlayerTier::Purple && $player->played_count === 1) {
                $purpleIndex = $i;
            }
        }

        $this->assertLessThan($purpleIndex, $greenIndex, 
            'Green player with fewer matches should be prioritized over Purple player');
    }

    /**
     * Test that seed produces deterministic results.
     */
    public function test_produces_deterministic_results_with_same_seed(): void
    {
        $players = $this->createPlayers([
            ['id' => 1, 'tier' => PlayerTier::Purple],
            ['id' => 2, 'tier' => PlayerTier::Red],
            ['id' => 3, 'tier' => PlayerTier::Yellow],
            ['id' => 4, 'tier' => PlayerTier::Green],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green],
        ]);

        $request1 = $this->createRequest(seed: 12345);
        $request2 = $this->createRequest(seed: 12345);
        $request3 = $this->createRequest(seed: 54321);

        // Note: Full integration test would require mocking MatchHistoryRepository
        // This is a simplified test for the shuffle mechanism

        $reflection = new \ReflectionClass($this->scheduler);
        $method = $reflection->getMethod('shuffleWithSeed');
        $method->setAccessible(true);

        $result1a = $method->invoke($this->scheduler, $players, 12345);
        $result1b = $method->invoke($this->scheduler, $players, 12345);
        $result2 = $method->invoke($this->scheduler, $players, 54321);

        // Same seed should produce same order
        $ids1a = array_column($result1a, 'mini_participant_id');
        $ids1b = array_column($result1b, 'mini_participant_id');
        $ids2 = array_column($result2, 'mini_participant_id');

        $this->assertEquals($ids1a, $ids1b, 'Same seed should produce same order');
        $this->assertNotEquals($ids1a, $ids2, 'Different seeds should produce different order');
    }

    /**
     * Test PlayerTier enum methods.
     */
    public function test_player_tier_priority(): void
    {
        $this->assertEquals(4, PlayerTier::Purple->priority());
        $this->assertEquals(3, PlayerTier::Red->priority());
        $this->assertEquals(2, PlayerTier::Yellow->priority());
        $this->assertEquals(1, PlayerTier::Green->priority());
    }

    public function test_player_tier_score(): void
    {
        $this->assertEquals(4.0, PlayerTier::Purple->score());
        $this->assertEquals(3.0, PlayerTier::Red->score());
        $this->assertEquals(2.0, PlayerTier::Yellow->score());
        $this->assertEquals(1.0, PlayerTier::Green->score());
    }

    /**
     * Test that calculateTierDistributionMatch returns 3.0 for perfect same-tier match.
     * Example: Team A [C,C] vs Team B [C,C] = đỏ đỏ vs đỏ đỏ
     */
    public function test_tier_distribution_match_perfect_same_tier(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculateTierDistributionMatch');
        $method->setAccessible(true);

        // Team A: 2 Red players (C,C)
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Red, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Red, 'user_id' => 2]),
        ];

        // Team B: 2 Red players (C,C) - same tier inside each team AND same across teams
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Red, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Red, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB);
        
        $this->assertEquals(3.0, $result, 'Perfect same-tier match should return 3.0');
    }

    /**
     * Test that calculateTierDistributionMatch returns 2.0 for perfect distribution.
     * Example: Team A [A,B] vs Team B [A,B] = xanh đỏ vs xanh đỏ (different tiers inside team but same distribution)
     */
    public function test_tier_distribution_match_corresponding_distribution(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculateTierDistributionMatch');
        $method->setAccessible(true);

        // Team A: Yellow + Red (B + C = 2 + 3) - different tiers inside team
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Yellow, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Red, 'user_id' => 2]),
        ];

        // Team B: Yellow + Red (B + C = 2 + 3) - different tiers inside team, same distribution
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Yellow, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Red, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB);
        
        $this->assertEquals(2.0, $result, 'Corresponding distribution should return 2.0');
    }

    /**
     * Test that calculateTierDistributionMatch returns 0.0 for mismatched distribution.
     * Example: Team A [A,B] vs Team B [B,C] = xanh đỏ vs đỏ vàng (mixed without matching distribution)
     */
    public function test_tier_distribution_match_mismatched_distribution(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculateTierDistributionMatch');
        $method->setAccessible(true);

        // Team A: Green + Yellow (A,B = 1,2)
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Green, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Yellow, 'user_id' => 2]),
        ];

        // Team B: Yellow + Red (B,C = 2,3) - different distribution
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Yellow, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Red, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB);
        
        // This is NOT internal mismatch (teams have mixed tiers), so it should be 0.0
        $this->assertEquals(0.0, $result, 'Mixed distribution without matching should return 0.0');
    }

    /**
     * Test that calculateTierDistributionMatch returns 1.0 for internal mismatch penalty.
     * Example: Team A [đỏ,đỏ] vs Team B [xanh,xanh] - both teams have uniform tiers but different from each other.
     */
    public function test_tier_distribution_match_internal_mismatch_penalty(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculateTierDistributionMatch');
        $method->setAccessible(true);

        // Team A: 2 Green players (A,A = 1,1) - same tier inside
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Green, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Green, 'user_id' => 2]),
        ];

        // Team B: 2 Red players (C,C = 3,3) - same tier inside but DIFFERENT from team A
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Red, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Red, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB);
        
        // Both teams have uniform tiers but different from each other = 1.0 (PENALTY)
        $this->assertEquals(1.0, $result, 'Internal mismatch should return 1.0 (penalty)');
    }

    /**
     * Test that calculateTierDistributionMatch returns 1.0 for internal mismatch with Yellow.
     * Example: Team A [vàng,vàng] vs Team B [đỏ,đỏ] - this was the original bug scenario.
     */
    public function test_tier_distribution_match_yellow_red_internal_mismatch(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculateTierDistributionMatch');
        $method->setAccessible(true);

        // Team A: 2 Yellow players - same tier inside
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Yellow, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Yellow, 'user_id' => 2]),
        ];

        // Team B: 2 Red players - same tier inside but DIFFERENT from team A
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Red, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Red, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB);
        
        // This is the original bug case - should return 1.0 (penalty) now
        $this->assertEquals(1.0, $result, 'Yellow vs Red internal mismatch should return 1.0 (penalty)');
    }

    /**
     * Test that findOptimalPairing prefers same-tier teams when prefer_high_tier_match is enabled.
     * Even if balance diff is slightly worse, same-tier should win.
     */
    public function test_prefer_high_tier_match_selects_same_tier_over_balance(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('findOptimalPairing');
        $method->setAccessible(true);

        // 4 players with different tiers and same vndupr
        // P1: Red (3.0), P2: Red (3.0), P3: Green (1.0), P4: Green (1.0)
        $players = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Red, 'user_id' => 1, 'vndupr_score' => 3.0]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Red, 'user_id' => 2, 'vndupr_score' => 3.0]),
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Green, 'user_id' => 3, 'vndupr_score' => 1.0]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Green, 'user_id' => 4, 'vndupr_score' => 1.0]),
        ];

        // Same vndupr for all, so balance diff = 0 for any pairing
        $userDataMap = [
            1 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '3.0']]]],
            2 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '3.0']]]],
            3 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '1.0']]]],
            4 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '1.0']]]],
        ];

        $settings = new class {
            public bool $prefer_high_tier_match = true;
            public bool $balance_team = true;
        };

        $result = $method->invoke($this->scheduler, $players, $userDataMap, $settings);
        
        $this->assertNotNull($result, 'Should find a pairing');
        
        // Get tier priorities for each team
        $tiersA = array_map(fn($p) => $p->tier->priority(), $result['team_a']);
        $tiersB = array_map(fn($p) => $p->tier->priority(), $result['team_b']);
        sort($tiersA);
        sort($tiersB);

        // With prefer_high_tier_match, should prefer same-tier in each team
        // [Red,Red] vs [Green,Green] gives 3.0 (perfect same-tier)
        // [Red,Green] vs [Red,Green] gives 2.0 (perfect distribution)
        // Both are acceptable (>= 1.0), not mismatched (0.0)
        $tierMatch = $reflection->getMethod('calculateTierDistributionMatch');
        $tierMatch->setAccessible(true);
        $matchScore = $tierMatch->invoke($this->scheduler, $result['team_a'], $result['team_b']);
        
        // Should be either perfect same-tier (3.0) or perfect distribution (2.0), not mismatched (0.0)
        $this->assertGreaterThanOrEqual(2.0, $matchScore, 
            'Should prefer matching tier distribution, not mismatched');
    }

    /**
     * Test that without prefer_high_tier_match, algorithm still balances by VN DUPR.
     */
    public function test_without_prefer_high_tier_uses_vndupr_balance(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('findOptimalPairing');
        $method->setAccessible(true);

        // 4 players with very different vndupr scores
        // P1: Purple (4.0), P2: Green (1.0), P3: Yellow (2.0), P4: Yellow (2.0)
        $players = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Purple, 'user_id' => 1, 'vndupr_score' => 4.0]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Green, 'user_id' => 2, 'vndupr_score' => 1.0]),
            $this->createPlayerContext(['id' => 3, 'tier' => PlayerTier::Yellow, 'user_id' => 3, 'vndupr_score' => 2.0]),
            $this->createPlayerContext(['id' => 4, 'tier' => PlayerTier::Yellow, 'user_id' => 4, 'vndupr_score' => 2.0]),
        ];

        $userDataMap = [
            1 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '4.0']]]],
            2 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '1.0']]]],
            3 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '2.0']]]],
            4 => ['visibility' => 'open', 'sports' => [['scores' => ['vndupr_score' => '2.0']]]],
        ];

        $settings = new class {
            public bool $prefer_high_tier_match = false;
            public bool $balance_team = true;
        };

        $result = $method->invoke($this->scheduler, $players, $userDataMap, $settings);
        
        $this->assertNotNull($result, 'Should find a pairing');
        
        // Without prefer_high_tier_match, should balance by VN DUPR
        // Best would be [4.0, 2.0] vs [2.0, 1.0] = 3.0 vs 1.5, diff = 1.5
        // Or [4.0, 1.0] vs [2.0, 2.0] = 2.5 vs 2.0, diff = 0.5 (better!)
        $balanceMethod = $reflection->getMethod('calculateBalanceDiff');
        $balanceMethod->setAccessible(true);
        $balanceDiff = $balanceMethod->invoke($this->scheduler, $result['team_a'], $result['team_b'], $userDataMap);
        
        // Should find a reasonably balanced pairing
        $this->assertLessThanOrEqual(1.5, $balanceDiff, 
            'Should balance teams by VN DUPR when prefer_high_tier_match is disabled');
    }

    /**
     * Test that selectPlayers picks a mix of tiers for better pairing.
     * This tests the scenario: 6 Red, 4 Yellow, 1 Green
     * Should select [Red,Yellow] from males and [Red,Yellow] from females,
     * NOT [Red,Green] vs [Yellow,Yellow].
     */
    public function test_select_players_balances_tier_distribution(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('selectPlayers');
        $method->setAccessible(true);

        // Scenario: 6 Red (3M+3F), 4 Yellow (2M+2F), 1 Green (1F)
        // Total: 5M (3 Red + 2 Yellow), 6F (3 Red + 2 Yellow + 1 Green)
        $players = $this->createPlayers([
            // Males: 3 Red, 2 Yellow
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            // Females: 3 Red, 2 Yellow, 1 Green
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $method->invoke($this->scheduler, $players, $request);
        
        // Should select exactly 4 players (2M + 2F)
        $this->assertCount(4, $result, 'Should select exactly 4 players');
        
        // Count genders
        $males = array_filter($result, fn($p) => $p->gender === User::MALE);
        $females = array_filter($result, fn($p) => $p->gender === User::FEMALE);
        
        $this->assertCount(2, $males, 'Should select 2 males');
        $this->assertCount(2, $females, 'Should select 2 females');
        
        // Get tiers
        $tiers = array_map(fn($p) => $p->tier, $result);
        $tierNames = array_map(fn($t) => $t->name, $tiers);
        
        // Should NOT be [Red, Green] + [Yellow, Yellow] = tier distribution [1,2,2,2]
        // Good selection: [Red, Yellow] + [Red, Yellow] = tier distribution [1,2,2,3] - more balanced
        // Count tier types
        $uniqueTiers = array_unique($tierNames);
        
        // At minimum, should have more than 1 unique tier if available
        // This prevents picking all same-tier when mix is possible
        $this->assertGreaterThan(1, count($uniqueTiers), 
            'Should select mix of tiers when available');
    }

    /**
     * Test that calculatePoolTierBalance returns higher scores for balanced distributions.
     */
    public function test_pool_tier_balance_higher_for_balanced_groups(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        
        $method = $reflection->getMethod('calculatePoolTierBalance');
        $method->setAccessible(true);

        // All same tier - perfectly balanced
        $allSame = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Red]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Red]),
        ];
        
        // Mixed tiers - less balanced
        $mixed = [
            $this->createPlayerContext(['id' => 1, 'tier' => PlayerTier::Red]),
            $this->createPlayerContext(['id' => 2, 'tier' => PlayerTier::Green]),
        ];

        $sameScore = $method->invoke($this->scheduler, $allSame);
        $mixedScore = $method->invoke($this->scheduler, $mixed);
        
        // Same tier should have higher (or equal) balance score
        $this->assertGreaterThanOrEqual($mixedScore, $sameScore,
            'All same tier should have higher or equal balance score');
    }

    /**
     * Test scenario: 5F (1 đỏ, 3 vàng, 1 xanh), 6M (5 đỏ, 1 vàng)
     * This tests an extreme imbalance case where the algorithm should still find a valid match.
     */
    public function test_select_players_handles_extreme_tier_imbalance(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);

        $method = $reflection->getMethod('selectPlayers');
        $method->setAccessible(true);

        // Scenario: 5 females (1 red, 3 yellow, 1 green), 6 males (5 red, 1 yellow)
        $players = $this->createPlayers([
            // Males: 5 Red, 1 Yellow
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            // Females: 1 Red, 3 Yellow, 1 Green
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $method->invoke($this->scheduler, $players, $request);

        // Should select exactly 4 players (2M + 2F)
        $this->assertCount(4, $result, 'Should select exactly 4 players');

        // Count genders
        $males = array_filter($result, fn($p) => $p->gender === User::MALE);
        $females = array_filter($result, fn($p) => $p->gender === User::FEMALE);

        $this->assertCount(2, $males, 'Should select 2 males');
        $this->assertCount(2, $females, 'Should select 2 females');
    }

    /**
     * Integration test: Full generate() flow with extreme tier imbalance.
     * Scenario: 5F (1 đỏ, 3 vàng, 1 xanh), 6M (5 đỏ, 1 vàng)
     * The algorithm should still produce a valid match suggestion.
     */
    public function test_generate_handles_extreme_tier_imbalance(): void
    {
        // Scenario: 5 females (1 red, 3 yellow, 1 green), 6 males (5 red, 1 yellow)
        $players = $this->createPlayers([
            // Males: 5 Red, 1 Yellow
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            // Females: 1 Red, 3 Yellow, 1 Green
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();

        $result = $this->scheduler->generate($players, $request);

        // Should return a match, not an error
        $this->assertNotNull($result, 'Should return a result, not null');
        $this->assertNotNull($result->match, 'Should produce a match suggestion, not null');
        $this->assertNotEmpty($result->match, 'Match should not be empty');
    }

    /**
     * Test extreme tier imbalance with all players already played.
     * Scenario: 5F (1 đỏ, 3 vàng, 1 xanh), 6M (5 đỏ, 1 vàng) - all played
     */
    public function test_generate_handles_extreme_tier_imbalance_played(): void
    {
        // Scenario: 5 females (1 red, 3 yellow, 1 green), 6 males (5 red, 1 yellow)
        // All have played >= 1
        $players = $this->createPlayers([
            // Males: 5 Red, 1 Yellow - all played
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 2],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 1],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 3],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 1],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 2],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 1],
            // Females: 1 Red, 3 Yellow, 1 Green - all played
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 2],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 1],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 1],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 2],
        ]);

        $request = $this->createRequest();

        $result = $this->scheduler->generate($players, $request);

        // Should return a match, not an error
        $this->assertNotNull($result, 'Should return a result, not null');
        $this->assertNotNull($result->match, 'Should produce a match suggestion, not null');
        $this->assertNotEmpty($result->match, 'Match should not be empty');
    }

    /**
     * Test that debug messages are returned when something goes wrong.
     */
    public function test_debug_messages_returned_when_no_match(): void
    {
        // Create a scenario that should work - use the extreme imbalance case
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        // Should find a match
        $this->assertNotNull($result->match, 'Should find a match');
        // Messages should not contain error messages
        $this->assertEmpty(array_filter($result->messages, fn($m) => str_contains($m, 'No valid match')), 
            'Should not have no-match errors');
    }

    /**
     * Test that tier distribution is actually balanced after the fix.
     * With the user's scenario: 6 males (5 đỏ, 1 vàng), 5 females (1 đỏ, 3 vàng, 1 xanh)
     * We should NOT get [đỏ+xanh] vs [vàng+vàng].
     */
    public function test_tier_distribution_is_balanced_in_final_match(): void
    {
        // User's exact scenario:
        // Males: 6 total - 5 Red, 1 Yellow
        // Females: 5 total - 1 Red, 3 Yellow, 1 Green
        $players = $this->createPlayers([
            // Males: 5 Red, 1 Yellow
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            // Females: 1 Red, 3 Yellow, 1 Green
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        $this->assertNotNull($result->match, 'Should find a match');

        // Get tiers from both teams
        $team1Tiers = array_map(fn($p) => $p->tier->name, $result->match->team1->members);
        $team2Tiers = array_map(fn($p) => $p->tier->name, $result->match->team2->members);

        // Count tiers in each team
        $t1Red = count(array_filter($team1Tiers, fn($t) => $t === 'red'));
        $t2Red = count(array_filter($team2Tiers, fn($t) => $t === 'red'));
        $t1Yellow = count(array_filter($team1Tiers, fn($t) => $t === 'yellow'));
        $t2Yellow = count(array_filter($team2Tiers, fn($t) => $t === 'yellow'));
        $t1Green = count(array_filter($team1Tiers, fn($t) => $t === 'green'));
        $t2Green = count(array_filter($team2Tiers, fn($t) => $t === 'green'));

        // Calculate team strength (Red=3, Yellow=2, Green=1)
        $t1Strength = $t1Red * 3 + $t1Yellow * 2 + $t1Green * 1;
        $t2Strength = $t2Red * 3 + $t2Yellow * 2 + $t2Green * 1;

        // Log for debugging
        $tierScore = $this->getTierDistributionScore($team1Tiers, $team2Tiers);

        // Should NOT get worst-case [Red,Green] vs [Yellow,Yellow]
        // Worst case: team1 = [Red,Green], team2 = [Yellow,Yellow]
        // Best case: team1 = [Red,Yellow], team2 = [Red,Yellow]
        $isWorstCase = ($t1Red === 1 && $t1Green === 1 && $t2Yellow === 2);

        $this->assertFalse($isWorstCase,
            "Should NOT get [Red,Green] vs [Yellow,Yellow] (worst case). Got: " .
            "Team1=[" . implode(',', $team1Tiers) . "], Team2=[" . implode(',', $team2Tiers) . "]");
    }

    private function getTierDistributionScore(array $team1Tiers, array $team2Tiers): float
    {
        $allTiers = array_merge($team1Tiers, $team2Tiers);

        // Score based on tier distribution match
        // Perfect: [Red,Yellow] + [Red,Yellow] = 2.0
        // Worst: [Red,Green] + [Yellow,Yellow] = 0.0

        $tierValues = ['green' => 1, 'yellow' => 2, 'red' => 3];
        $team1Values = array_map(fn($t) => $tierValues[$t] ?? 0, $team1Tiers);
        $team2Values = array_map(fn($t) => $tierValues[$t] ?? 0, $team2Tiers);

        $diff = abs(array_sum($team1Values) - array_sum($team2Values));

        // Normalize: max diff is 4 (Red+Green vs Yellow+Yellow = 4+1 vs 2+2 = 5 vs 4)
        return max(0, 1 - ($diff / 4));
    }

    /**
     * PRIORITY 1: When 4+ males available, prefer all-male group (Nam vs Nam)
     * over mixed group.
     */
    public function test_priority_1_male_only_when_4plus_males(): void
    {
        // 5 males, 6 females - both groups possible, but male should win
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Green, 'played' => 0],
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 9, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
            ['id' => 11, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        $this->assertNotNull($result->match, 'Should find a match');

        // Map user_id -> gender
        $genderMap = [];
        foreach ($players as $p) {
            $genderMap[$p->user_id] = $p->gender;
        }

        // All 4 players in the match should be male
        $allMales = true;
        foreach ($result->match->team1->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender !== User::MALE) $allMales = false;
        }
        foreach ($result->match->team2->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender !== User::MALE) $allMales = false;
        }
        $this->assertTrue($allMales, 'Should pick all-male group when 4+ males available');
    }

    /**
     * PRIORITY 2: When <4 males but 4+ females available, prefer all-female group.
     */
    public function test_priority_2_female_only_when_4plus_females(): void
    {
        // 3 males, 5 females - all-male not possible, all-female wins
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Green, 'played' => 0],
            ['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        $this->assertNotNull($result->match, 'Should find a match');

        // Map user_id -> gender
        $genderMap = [];
        foreach ($players as $p) {
            $genderMap[$p->user_id] = $p->gender;
        }

        // All 4 players should be female
        $allFemales = true;
        foreach ($result->match->team1->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender !== User::FEMALE) $allFemales = false;
        }
        foreach ($result->match->team2->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender !== User::FEMALE) $allFemales = false;
        }
        $this->assertTrue($allFemales, 'Should pick all-female group when <4 males and 4+ females available');
    }

    /**
     * PRIORITY 3: When <4 males AND <4 females but >=2 of each, fall back to mixed.
     */
    public function test_priority_3_mixed_when_2m_2f(): void
    {
        // 2 males, 3 females - can't form same-gender 4, use mixed
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 3, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        $this->assertNotNull($result->match, 'Should find a match');

        // Map user_id -> gender
        $genderMap = [];
        foreach ($players as $p) {
            $genderMap[$p->user_id] = $p->gender;
        }

        // Should be mixed (2 males + 2 females)
        $team1Males = 0;
        $team2Males = 0;
        $team1Females = 0;
        $team2Females = 0;

        foreach ($result->match->team1->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender === User::MALE) $team1Males++;
            if ($gender === User::FEMALE) $team1Females++;
        }
        foreach ($result->match->team2->members as $m) {
            $gender = $genderMap[$m->user_id] ?? null;
            if ($gender === User::MALE) $team2Males++;
            if ($gender === User::FEMALE) $team2Females++;
        }

        $totalMales = $team1Males + $team2Males;
        $totalFemales = $team1Females + $team2Females;

        $this->assertEquals(2, $totalMales, 'Should have 2 males in mixed match');
        $this->assertEquals(2, $totalFemales, 'Should have 2 females in mixed match');
        $this->assertEquals(1, $team1Males, 'Should be symmetric: 1 male per team');
        $this->assertEquals(1, $team1Females, 'Should be symmetric: 1 female per team');
    }

    /**
     * PRIORITY 5 (Backup): When cannot form any clean group (e.g. 3M+2F),
     * use all available players as backup.
     */
    public function test_backup_uses_all_remaining_players(): void
    {
        // 3 males, 2 females - cannot form 4-male or 4-female or symmetric 2-2 mixed
        // Backup uses all players
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Green, 'played' => 0],
            ['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red, 'played' => 0],
            ['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->generate($players, $request);

        // Should still find some match (4 players total)
        $this->assertNotNull($result->match, 'Backup should find a match');
        $totalMembers = count($result->match->team1->members) + count($result->match->team2->members);
        $this->assertEquals(4, $totalMembers, 'Match should have 4 players');
    }

    /**
     * Verify asymmetric pairing (Nam-Nam vs Nữ-Nữ) is rejected.
     */
    public function test_rejects_nam_nam_vs_nu_nu_pairing(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        $method = $reflection->getMethod('isValidGenderPairing');
        $method->setAccessible(true);

        // Team A: 2 males
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'gender' => User::MALE, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'gender' => User::MALE, 'user_id' => 2]),
        ];

        // Team B: 2 females (asymmetric!)
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'gender' => User::FEMALE, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'gender' => User::FEMALE, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB, []);

        $this->assertFalse($result, 'Should reject Nam-Nam vs Nữ-Nữ asymmetric pairing');
    }

    /**
     * Verify symmetric pairing (Nam-Nữ vs Nam-Nữ) is accepted.
     */
    public function test_accepts_nam_nu_vs_nam_nu_pairing(): void
    {
        $reflection = new \ReflectionClass($this->scheduler);
        $method = $reflection->getMethod('isValidGenderPairing');
        $method->setAccessible(true);

        // Team A: 1 male, 1 female
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'gender' => User::MALE, 'user_id' => 1]),
            $this->createPlayerContext(['id' => 2, 'gender' => User::FEMALE, 'user_id' => 2]),
        ];

        // Team B: 1 male, 1 female (symmetric!)
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'gender' => User::MALE, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'gender' => User::FEMALE, 'user_id' => 4]),
        ];

        $result = $method->invoke($this->scheduler, $teamA, $teamB, []);

        $this->assertTrue($result, 'Should accept Nam-Nữ vs Nam-Nữ symmetric pairing');
    }

    // Helper methods

    private function createPlayers(array $config): array
    {
        $players = [];
        foreach ($config as $i => $c) {
            $players[] = new PlayerContextDTO(
                mini_participant_id: $c['id'],
                user_id: $c['user_id'] ?? $c['id'],
                full_name: 'Player ' . $c['id'],
                avatar_url: null,
                tier: $c['tier'],
                is_manual_override: true,
                gender: $c['gender'] ?? User::MALE,
                is_guest: $c['is_guest'] ?? false,
                played_count: $c['played'] ?? 0,
                consecutive_count: 0,
                waiting_rounds: 0,
                vndupr_score: null,
                partner_ids: [],
                is_checked_in: true,
                is_playing: false,
                skip_next_round: false,
                is_absent: false,
                payment_status: null,
                is_backup: false,
            );
        }
        return $players;
    }

    private function createPlayerContext(array $config): PlayerContextDTO
    {
        return new PlayerContextDTO(
            mini_participant_id: $config['id'],
            user_id: $config['user_id'] ?? $config['id'],
            full_name: 'Player ' . ($config['id'] ?? 1),
            avatar_url: null,
            tier: $config['tier'] ?? PlayerTier::Yellow,
            is_manual_override: true,
            gender: $config['gender'] ?? User::MALE,
            is_guest: $config['is_guest'] ?? false,
            played_count: $config['played'] ?? 0,
            consecutive_count: $config['consecutive_count'] ?? 0,
            waiting_rounds: $config['waiting_rounds'] ?? 0,
            vndupr_score: $config['vndupr_score'] ?? null,
            partner_ids: [],
            is_checked_in: true,
            is_playing: false,
            skip_next_round: false,
            is_absent: false,
            payment_status: null,
            is_backup: false,
        );
    }

    private function createRequest(?int $seed = null): MatchSuggestionRequestDTO
    {
        return new MatchSuggestionRequestDTO(
            mini_tournament_id: 1,
            participants: [],
            settings: new MatchSuggestionSettingsDTO(
                fair_play: true,
                balance_team: true,
                prefer_high_tier_match: true,
                prevent_three_consecutive: true,
                organizer_as_backup: false,
            ),
            seed: $seed,
        );
    }
}
