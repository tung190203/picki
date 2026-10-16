<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — Update guard for `player_per_team`.
 *
 * Once a tournament has at least one match with results (MatchResult rows),
 * `player_per_team` must NOT be changeable — otherwise matches played under
 * 2-player rules would have to be re-scored under 3-player Rally rules.
 */
class UpdateTournamentPlayerPerTeamGuardTest extends TestCase
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

    protected function makeTournament(int $playerPerTeam = 2, ?User $user = null): Tournament
    {
        $user = $user ?? $this->makeUser();
        $sport = Sport::factory()->create();
        $location = \App\Models\CompetitionLocation::create([
            'name' => 'Loc ' . uniqid(),
            'address' => '123 Street',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'is_banned' => false,
        ]);

        $tournament = Tournament::create([
            'name' => 'Guard test ' . uniqid(),
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

        // Add the user as organizer so update is allowed.
        \App\Models\TournamentStaff::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'role' => \App\Models\TournamentStaff::ROLE_ORGANIZER,
        ]);

        return $tournament;
    }

    public function test_update_player_per_team_allowed_when_no_matches(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournament(2, $user);

        $response = $this->actingAs($user)->postJson(
            "/api/tournaments/update/{$tournament->id}",
            ['player_per_team' => 3]
        );

        $response->assertStatus(200);
    }

    public function test_update_player_per_team_blocked_when_matches_have_results(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournament(2, $user);

        // Set up minimal tournament_type + group + match + result
        $type = TournamentType::createWithFormat($tournament->id, TournamentType::FORMAT_MIXED, []);
        $group = Group::create([
            'tournament_type_id' => $type->id,
            'name' => 'A',
        ]);

        $team1 = Team::create(['name' => 'T1', 'tournament_id' => $tournament->id]);
        $team2 = Team::create(['name' => 'T2', 'tournament_id' => $tournament->id]);

        $match = Matches::create([
            'tournament_type_id' => $type->id,
            'group_id' => $group->id,
            'round' => 1,
            'home_team_id' => $team1->id,
            'away_team_id' => $team2->id,
            'leg' => 1,
            'status' => 'completed',
            'winner_id' => $team1->id,
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_id' => $team1->id,
            'participant_id' => $team1->id,
            'score' => 11,
            'set_number' => 1,
            'won_match' => true,
        ]);
        MatchResult::create([
            'match_id' => $match->id,
            'team_id' => $team2->id,
            'participant_id' => $team2->id,
            'score' => 7,
            'set_number' => 1,
            'won_match' => false,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/tournaments/update/{$tournament->id}",
            ['player_per_team' => 3]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['player_per_team']);
        $this->assertDatabaseHas('tournaments', [
            'id' => $tournament->id,
            'player_per_team' => 2, // unchanged
        ]);
    }

    public function test_update_player_per_team_same_value_is_not_blocked(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournament(2, $user);

        $type = TournamentType::createWithFormat($tournament->id, TournamentType::FORMAT_MIXED, []);
        $group = Group::create([
            'tournament_type_id' => $type->id,
            'name' => 'A',
        ]);
        $team1 = Team::create(['name' => 'T1', 'tournament_id' => $tournament->id]);
        $team2 = Team::create(['name' => 'T2', 'tournament_id' => $tournament->id]);
        $match = Matches::create([
            'tournament_type_id' => $type->id,
            'group_id' => $group->id,
            'round' => 1,
            'home_team_id' => $team1->id,
            'away_team_id' => $team2->id,
            'leg' => 1,
            'status' => 'completed',
            'winner_id' => $team1->id,
        ]);
        MatchResult::create([
            'match_id' => $match->id,
            'team_id' => $team1->id,
            'participant_id' => $team1->id,
            'score' => 11,
            'set_number' => 1,
            'won_match' => true,
        ]);

        // Re-send same value → should NOT trigger the guard.
        $response = $this->actingAs($user)->postJson(
            "/api/tournaments/update/{$tournament->id}",
            ['player_per_team' => 2]
        );

        $response->assertStatus(200);
    }
}
