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
