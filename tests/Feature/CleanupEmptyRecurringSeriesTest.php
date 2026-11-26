<?php

namespace Tests\Feature;

use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupEmptyRecurringSeriesTest extends TestCase
{
    use RefreshDatabase;

    private int $sportId;

    protected function setUp(): void
    {
        parent::setUp();
        $sport = Sport::create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        $this->sportId = $sport->id;
    }

    public function test_series_is_cancelled_when_more_than_10_occurrences_are_empty(): void
    {
        Queue::fake();

        $creator = User::factory()->create();
        $seriesId = (string) Str::uuid();

        // Create 11 past empty occurrences (> 24h ago)
        for ($i = 1; $i <= 11; $i++) {
            MiniTournament::create([
                'name' => "Kèo lặp #{$i}",
                'sport_id' => $this->sportId,
                'created_by' => $creator->id,
                'status' => MiniTournament::STATUS_OPEN,
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHours(2),
                'recurrence_series_id' => $seriesId,
                'recurring_schedule' => ['period' => 'weekly', 'week_days' => [1]],
                'has_fee' => false,
            ]);
        }

        // Create 1 future occurrence in the same series
        $future = MiniTournament::create([
            'name' => 'Kèo tương lai',
            'sport_id' => $this->sportId,
            'created_by' => $creator->id,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'recurrence_series_id' => $seriesId,
            'recurring_schedule' => ['period' => 'weekly', 'week_days' => [1]],
            'has_fee' => false,
        ]);

        $this->artisan('tournaments:cleanup-empty')->assertSuccessful();

        // Future occurrence should be soft-deleted and series marked cancelled
        $this->assertSoftDeleted('mini_tournaments', ['id' => $future->id]);
        $this->assertNotNull($future->fresh()->recurrence_series_cancelled_at);
    }

    public function test_series_is_not_cancelled_at_exactly_10_empty_occurrences(): void
    {
        Queue::fake();

        $creator = User::factory()->create();
        $seriesId = (string) Str::uuid();

        // Exactly 10 past empty occurrences (threshold is > 10)
        for ($i = 1; $i <= 10; $i++) {
            MiniTournament::create([
                'name' => "Kèo lặp #{$i}",
                'sport_id' => $this->sportId,
                'created_by' => $creator->id,
                'status' => MiniTournament::STATUS_OPEN,
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHours(2),
                'recurrence_series_id' => $seriesId,
                'recurring_schedule' => ['period' => 'weekly', 'week_days' => [1]],
                'has_fee' => false,
            ]);
        }

        $future = MiniTournament::create([
            'name' => 'Kèo tương lai',
            'sport_id' => $this->sportId,
            'created_by' => $creator->id,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'recurrence_series_id' => $seriesId,
            'recurring_schedule' => ['period' => 'weekly', 'week_days' => [1]],
            'has_fee' => false,
        ]);

        $this->artisan('tournaments:cleanup-empty')->assertSuccessful();

        $this->assertNull($future->fresh()->deleted_at);
        $this->assertNull($future->fresh()->recurrence_series_cancelled_at);
    }
}
