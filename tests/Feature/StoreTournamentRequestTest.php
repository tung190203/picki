<?php

namespace Tests\Feature;

use App\Models\Sport;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — Rally Scoring configuration on Tournament creation.
 *
 * Acceptance criteria:
 * - player_per_team = 2, 3, 4 → 200; player_per_team = 5 → 422.
 * - Server stamps scoring_type correctly (2 → side_out, ≥3 → rally).
 * - For rally: default stage_points = [10, 20, 30].
 * - For side_out: no stage_points key in match_rules.
 */
class StoreTournamentRequestTest extends TestCase
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

    protected function makeLocation(): \App\Models\CompetitionLocation
    {
        return \App\Models\CompetitionLocation::create([
            'name' => 'Test Location ' . uniqid(),
            'address' => '123 Test Street',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'is_banned' => false,
        ]);
    }

    protected function makeTournamentPayload(int $playerPerTeam, int $locationId): array
    {
        return [
            'sport_id' => Sport::value('id') ?? Sport::factory()->create()->id,
            'name' => 'Test Tournament ' . uniqid(),
            'competition_location_id' => $locationId,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => $playerPerTeam,
            'duration' => 1,
        ];
    }

    /**
     * Helper: create a tournament + two teams + a mixed tournament type.
     * Returns [Tournament $tournament, TournamentType $type].
     */
    protected function makeRallyTournament(int $playerPerTeam): array
    {
        $user = $this->makeUser();
        $sport = Sport::factory()->create();
        $location = $this->makeLocation();

        $tournament = Tournament::create([
            'name' => 'Rally Test ' . uniqid(),
            'sport_id' => $sport->id,
            'competition_location_id' => $location->id,
            'start_date' => now()->addDay(),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => $playerPerTeam,
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'duration' => 1,
        ]);

        \App\Models\Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        \App\Models\Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        $type = TournamentType::createWithFormat($tournament->id, TournamentType::FORMAT_MIXED, [
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
            ],
            'format_specific_config' => [[
                'seeding_rules' => [1, 2, 3],
                'ranking' => [1, 4, 5, 2, 3],
                'pool_stage' => [
                    'name' => 'Vòng bảng',
                    'number_competing_teams' => 2,
                    'num_advancing_teams' => 2,
                ],
            ]],
        ]);

        return [$tournament, $type];
    }

    // -------------------------------------------------------------------------
    // player_per_team validation
    // -------------------------------------------------------------------------

    public function test_player_per_team_2_is_accepted(): void
    {
        $user = $this->makeUser();
        $location = $this->makeLocation();
        $payload = $this->makeTournamentPayload(2, $location->id);

        $response = $this->actingAs($user)->postJson('/api/tournaments/store', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournaments', ['id' => $response->json('data.id'), 'player_per_team' => 2]);
    }

    public function test_player_per_team_3_is_accepted(): void
    {
        $user = $this->makeUser();
        $location = $this->makeLocation();
        $payload = $this->makeTournamentPayload(3, $location->id);

        $response = $this->actingAs($user)->postJson('/api/tournaments/store', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournaments', ['id' => $response->json('data.id'), 'player_per_team' => 3]);
    }

    public function test_player_per_team_4_is_accepted(): void
    {
        $user = $this->makeUser();
        $location = $this->makeLocation();
        $payload = $this->makeTournamentPayload(4, $location->id);

        $response = $this->actingAs($user)->postJson('/api/tournaments/store', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournaments', ['id' => $response->json('data.id'), 'player_per_team' => 4]);
    }

    public function test_player_per_team_5_is_rejected(): void
    {
        $user = $this->makeUser();
        $location = $this->makeLocation();
        $payload = $this->makeTournamentPayload(5, $location->id);

        $response = $this->actingAs($user)->postJson('/api/tournaments/store', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['player_per_team']);
    }

    public function test_player_per_team_1_is_rejected(): void
    {
        $user = $this->makeUser();
        $location = $this->makeLocation();
        $payload = $this->makeTournamentPayload(1, $location->id);

        $response = $this->actingAs($user)->postJson('/api/tournaments/store', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['player_per_team']);
    }

    // -------------------------------------------------------------------------
    // server-side scoring_type + stage_points
    // -------------------------------------------------------------------------

    public function test_player_per_team_2_stamps_side_out_and_strips_stage_points(): void
    {
        $user = $this->makeUser();
        $sport = Sport::factory()->create();

        // 1. Create tournament with player_per_team = 2
        $tournament = Tournament::create([
            'name' => 'Side-out test ' . uniqid(),
            'sport_id' => $sport->id,
            'competition_location_id' => $this->makeLocation()->id,
            'start_date' => now()->addDay(),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => 2,
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'duration' => 1,
        ]);

        // 2. Create tournament type (side-out)
        \App\Models\Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        \App\Models\Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        $payload = [
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_MIXED,
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
                'stage_points' => [10, 20, 30], // client-sent but must be stripped
            ],
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

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(200);

        $type = $tournament->fresh()->tournamentTypes()->first();
        $this->assertSame('side_out', $type->match_rules['scoring_type']);
        $this->assertArrayNotHasKey('stage_points', $type->match_rules);
    }

    public function test_player_per_team_3_stamps_rally_and_sets_default_stage_points(): void
    {
        $user = $this->makeUser();
        $sport = Sport::factory()->create();

        $tournament = Tournament::create([
            'name' => 'Rally test ' . uniqid(),
            'sport_id' => $sport->id,
            'competition_location_id' => $this->makeLocation()->id,
            'start_date' => now()->addDay(),
            'participant' => 'team',
            'max_team' => 8,
            'player_per_team' => 3,
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'duration' => 1,
        ]);

        \App\Models\Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        \App\Models\Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        $payload = [
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_MIXED,
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
            ], // no stage_points sent → server defaults to [10,20,30]
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

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);
        $response->assertStatus(200);

        $type = $tournament->fresh()->tournamentTypes()->first();
        $this->assertSame('rally', $type->match_rules['scoring_type']);
        $this->assertSame([10, 20, 30], array_values($type->match_rules['stage_points']));
    }
}
