<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — stage_points validation + side_out stripping.
 */
class TournamentTypeStagePointsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(): User
    {
        return User::factory()->create([
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ]);
    }

    protected function makeRallyTournament(): Tournament
    {
        $user = $this->makeUser();
        $sport = Sport::factory()->create();
        $location = \App\Models\CompetitionLocation::create([
            'name' => 'Loc ' . uniqid(),
            'address' => '123 Street',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'is_banned' => false,
        ]);

        $tournament = Tournament::create([
            'name' => 'Stage points test ' . uniqid(),
            'sport_id' => $sport->id,
            'competition_location_id' => $location->id,
            'start_date' => now()->addDay(),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => 3, // rally
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'duration' => 1,
        ]);

        Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        return $tournament;
    }

    protected function makeSideOutTournament(): Tournament
    {
        $user = $this->makeUser();
        $sport = Sport::factory()->create();
        $location = \App\Models\CompetitionLocation::create([
            'name' => 'Loc ' . uniqid(),
            'address' => '123 Street',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'is_banned' => false,
        ]);

        $tournament = Tournament::create([
            'name' => 'Side-out test ' . uniqid(),
            'sport_id' => $sport->id,
            'competition_location_id' => $location->id,
            'start_date' => now()->addDay(),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => 2, // side_out
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'duration' => 1,
        ]);

        Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        return $tournament;
    }

    protected function buildPayload(Tournament $tournament, array $matchRules): array
    {
        return [
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_MIXED,
            'match_rules' => $matchRules,
            'format_specific_config' => [[
                'seeding_rules' => [1, 2, 3],
                'ranking' => [1, 4, 5, 2, 3],
                'pool_stage' => [
                    'name' => 'Vòng bảng',
                    'number_competing_teams' => 2,
                    'num_advancing_teams' => 2,
                ],
            ]],
        ];
    }

    // -------------------------------------------------------------------------
    // Rally: custom stage_points accepted when valid
    // -------------------------------------------------------------------------

    public function test_rally_custom_stage_points_with_large_gap_is_accepted(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [8, 16, 24],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(200);

        $type = $tournament->fresh()->tournamentTypes()->first();
        $this->assertSame([8, 16, 24], array_values($type->match_rules['stage_points']));
    }

    public function test_rally_custom_stage_points_with_4_stages_is_accepted(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [10, 20, 30, 40],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(200);

        $type = $tournament->fresh()->tournamentTypes()->first();
        $this->assertSame([10, 20, 30, 40], array_values($type->match_rules['stage_points']));
    }

    // -------------------------------------------------------------------------
    // Rally: invalid stage_points rejected
    // -------------------------------------------------------------------------

    public function test_rally_invalid_gap_is_rejected(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [8, 16, 20], // gap 4 < 5
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(422);
    }

    public function test_rally_count_below_min_is_rejected(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [10],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(422);
    }

    public function test_rally_count_above_max_is_rejected(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [10, 20, 30, 40, 50, 60],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(422);
    }

    public function test_rally_not_strictly_increasing_is_rejected(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeRallyTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [12, 10, 30],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Side-out: stage_points stripped
    // -------------------------------------------------------------------------

    public function test_side_out_strips_stage_points(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeSideOutTournament();

        $payload = $this->buildPayload($tournament, [
            'sets_per_match' => 1,
            'points_to_win_set' => 11,
            'stage_points' => [10, 20, 30], // client sends but server must strip
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(200);

        $type = $tournament->fresh()->tournamentTypes()->first();
        $this->assertSame('side_out', $type->match_rules['scoring_type']);
        $this->assertArrayNotHasKey('stage_points', $type->match_rules);
    }
}
