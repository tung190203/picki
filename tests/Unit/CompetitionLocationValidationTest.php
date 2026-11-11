<?php

namespace Tests\Unit;

use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use Tests\TestCase;

class CompetitionLocationValidationTest extends TestCase
{
    public function test_store_mini_tournament_request_allows_nullable_competition_location_id(): void
    {
        $request = new StoreMiniTournamentRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('competition_location_id', $rules);
        $this->assertContains('nullable', $rules['competition_location_id']);
        $this->assertNotContains('required', $rules['competition_location_id']);
    }

    public function test_store_tournament_request_allows_nullable_competition_location_id(): void
    {
        $request = new StoreTournamentRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('competition_location_id', $rules);
        $this->assertContains('nullable', $rules['competition_location_id']);
        $this->assertNotContains('required', $rules['competition_location_id']);
    }

    public function test_update_tournament_request_allows_nullable_competition_location_id(): void
    {
        $request = new UpdateTournamentRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('competition_location_id', $rules);
        $this->assertContains('nullable', $rules['competition_location_id']);
        $this->assertNotContains('required', $rules['competition_location_id']);
    }

    public function test_update_mini_tournament_request_allows_nullable_competition_location_id(): void
    {
        $request = new UpdateMiniTournamentRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('competition_location_id', $rules);
        $this->assertContains('nullable', $rules['competition_location_id']);
        $this->assertNotContains('required', $rules['competition_location_id']);
    }
}

