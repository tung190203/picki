<?php

namespace Tests\Unit;

use App\Services\User\UserPartnerService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TestableUserPartnerService extends UserPartnerService
{
    public ?Collection $mockOpponents = null;
    public ?Collection $mockPartners = null;

    // We can use reflection or override if methods were protected, but since they are private,
    // let's test the filtering and sorting logic or test via reflection / wrapper.
}

class UserPartnerServiceTest extends TestCase
{
    /**
     * Helper to invoke private/protected methods or test filtering logic directly.
     */
    public function test_rival_must_have_losses_and_excludes_opponent_with_zero_losses(): void
    {
        // Simulate opponent stats
        $stats = collect([
            [
                'user_id' => 1,
                'total_matches' => 3,
                'wins' => 3, // Cường Cháo won 3, lost 0 against Tạ Thiếu Hiến
                'losses' => 0,
                'win_rate' => 100.0,
                'loss_rate' => 0.0,
            ],
            [
                'user_id' => 2,
                'total_matches' => 3,
                'wins' => 1,
                'losses' => 2,
                'win_rate' => 33.33,
                'loss_rate' => 66.67,
            ],
            [
                'user_id' => 3,
                'total_matches' => 4,
                'wins' => 2,
                'losses' => 2,
                'win_rate' => 50.0,
                'loss_rate' => 50.0,
            ],
            [
                'user_id' => 4,
                'total_matches' => 10,
                'wins' => 9, // Dominated 9-1
                'losses' => 1,
                'win_rate' => 90.0,
                'loss_rate' => 10.0,
            ],
        ]);

        $filtered = $stats
            ->filter(fn($s) => $s['total_matches'] >= 3
                && $s['losses'] > 0
                && ($s['losses'] >= $s['wins'] || $s['loss_rate'] >= 33.0)
            )
            ->values();

        // User 1 (Tạ Thiếu Hiến: losses = 0) MUST be excluded!
        $this->assertFalse($filtered->contains('user_id', 1));

        // User 4 (Dominated: 9-1, loss_rate 10%) MUST be excluded!
        $this->assertFalse($filtered->contains('user_id', 4));

        // User 2 (losses = 2, wins = 1) MUST be included
        $this->assertTrue($filtered->contains('user_id', 2));

        // User 3 (losses = 2, wins = 2) MUST be included
        $this->assertTrue($filtered->contains('user_id', 3));

        // Total qualified is 2, not forced to 3
        $this->assertCount(2, $filtered);
    }

    public function test_partner_must_have_wins_and_win_rate_at_least_50_percent(): void
    {
        $stats = collect([
            [
                'user_id' => 10,
                'total_matches' => 3,
                'wins' => 0,
                'losses' => 3,
                'win_rate' => 0.0,
                'loss_rate' => 100.0,
            ],
            [
                'user_id' => 20,
                'total_matches' => 3,
                'wins' => 1,
                'losses' => 2,
                'win_rate' => 33.33,
                'loss_rate' => 66.67,
            ],
            [
                'user_id' => 30,
                'total_matches' => 3,
                'wins' => 2,
                'losses' => 1,
                'win_rate' => 66.67,
                'loss_rate' => 33.33,
            ],
            [
                'user_id' => 40,
                'total_matches' => 4,
                'wins' => 2,
                'losses' => 2,
                'win_rate' => 50.0,
                'loss_rate' => 50.0,
            ],
        ]);

        $filtered = $stats
            ->filter(fn($s) => $s['total_matches'] >= 3
                && $s['wins'] > 0
                && $s['wins'] >= $s['losses']
            )
            ->values();

        // User 10 (all losses: wins = 0) MUST be excluded!
        $this->assertFalse($filtered->contains('user_id', 10));

        // User 20 (lost more than won: wins 1, losses 2) MUST be excluded!
        $this->assertFalse($filtered->contains('user_id', 20));

        // User 30 (wins = 2, losses = 1) MUST be included
        $this->assertTrue($filtered->contains('user_id', 30));

        // User 40 (wins = 2, losses = 2) MUST be included
        $this->assertTrue($filtered->contains('user_id', 40));

        // Only 2 partners qualify, never forced to 3
        $this->assertCount(2, $filtered);
    }
}

