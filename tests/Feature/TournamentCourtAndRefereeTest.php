<?php

namespace Tests\Feature;

use App\Models\CourtAssignment;
use App\Models\FreeMatch;
use App\Models\Group;
use App\Models\Matches;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Models\TournamentStaff;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TournamentCourtAndRefereeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ref1;
    private User $ref2;
    private User $player1;
    private User $player2;
    private User $player3;
    private User $player4;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->admin = User::factory()->create(['full_name' => 'Admin User']);
        $this->ref1 = User::factory()->create(['full_name' => 'Minh Quoc']);
        $this->ref2 = User::factory()->create(['full_name' => 'Bao Quoc']);
        $this->player1 = User::factory()->create(['full_name' => 'Player One']);
        $this->player2 = User::factory()->create(['full_name' => 'Player Two']);
        $this->player3 = User::factory()->create(['full_name' => 'Player Three']);
        $this->player4 = User::factory()->create(['full_name' => 'Player Four']);
    }

    private function makeTournament(string $name = 'Picki Cup'): array
    {
        $tournament = Tournament::create([
            'name' => $name,
            'sport_id' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'duration' => 2880,
            'participant' => 'team',
            'status' => Tournament::OPEN,
            'created_by' => $this->admin->id,
        ]);

        TournamentStaff::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->admin->id,
            'role' => TournamentStaff::ROLE_ORGANIZER,
        ]);
        TournamentStaff::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->ref1->id,
            'role' => TournamentStaff::ROLE_REFEREE,
        ]);
        TournamentStaff::create([
            'tournament_id' => $tournament->id,
            'user_id' => $this->ref2->id,
            'role' => TournamentStaff::ROLE_REFEREE,
        ]);

        $type = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_MIXED,
            'match_rules' => '{}',
        ]);

        return [$tournament, $type];
    }

    private function makeTeam(Tournament $tournament, User $user, string $name): Team
    {
        $team = Team::create([
            'name' => $name,
            'tournament_id' => $tournament->id,
        ]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $user->id]);

        return $team;
    }

    private function makeMatch(TournamentType $type, ?Group $group, Team $home, Team $away, array $attrs = []): Matches
    {
        return Matches::create(array_merge([
            'tournament_type_id' => $type->id,
            'group_id' => $group?->id,
            'round' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status' => Matches::STATUS_PENDING,
            'live_status' => Matches::LIVE_STATUS_WAITING,
            'is_bye' => false,
        ], $attrs));
    }

    public function test_admin_can_crud_courts_and_cannot_delete_court_with_live_match(): void
    {
        [$tournament, $type] = $this->makeTournament();
        $t1 = $this->makeTeam($tournament, $this->player1, 'Team 1');
        $t2 = $this->makeTeam($tournament, $this->player2, 'Team 2');

        // Create court
        $res = $this->actingAs($this->admin, 'api')
            ->postJson("/api/tournaments/{$tournament->id}/courts", ['name' => 'Sân 1']);
        $res->assertCreated();
        $courtId = $res->json('data.id');

        // Assign referee
        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/courts/{$courtId}", ['referee_user_id' => $this->ref1->id])
            ->assertOk()
            ->assertJsonPath('data.referee.user_id', $this->ref1->id);

        // Live match blocks delete
        $match = $this->makeMatch($type, null, $t1, $t2, [
            'court_id' => $courtId,
            'live_status' => Matches::LIVE_STATUS_PLAYING,
            'referee_id' => $this->ref1->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/courts/{$courtId}")
            ->assertStatus(409);

        // Once completed, delete succeeds and match keeps trashed court relation
        $match->update(['status' => Matches::STATUS_COMPLETED, 'live_status' => Matches::LIVE_STATUS_FINISHED]);
        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/courts/{$courtId}")
            ->assertOk();

        $this->assertSoftDeleted('tournament_courts', ['id' => $courtId]);
        $this->assertEquals('Sân 1', $match->fresh()->tournamentCourt->name);
    }

    public function test_court_board_splits_group_round_robin_and_auto_assigns(): void
    {
        [$tournament, $type] = $this->makeTournament();
        $c1 = TournamentCourt::create(['tournament_id' => $tournament->id, 'name' => 'Sân 1', 'sort_order' => 1, 'referee_user_id' => $this->ref1->id]);
        $c2 = TournamentCourt::create(['tournament_id' => $tournament->id, 'name' => 'Sân 2', 'sort_order' => 2, 'referee_user_id' => $this->ref2->id]);

        $groupA = Group::create(['tournament_type_id' => $type->id, 'name' => 'A']);
        $t1 = $this->makeTeam($tournament, $this->player1, 'T1');
        $t2 = $this->makeTeam($tournament, $this->player2, 'T2');

        $m1 = $this->makeMatch($type, $groupA, $t1, $t2, ['scheduled_at' => now()->addMinutes(10)]);
        $m2 = $this->makeMatch($type, $groupA, $t1, $t2, ['scheduled_at' => now()->addMinutes(20)]);
        $m3 = $this->makeMatch($type, $groupA, $t1, $t2, ['scheduled_at' => now()->addMinutes(30)]);

        // Split group A across Sân 1 and Sân 2
        $res = $this->actingAs($this->admin, 'api')
            ->putJson("/api/tournaments/{$tournament->id}/court-board/units", [
                'unit_type' => 'group',
                'group_id' => $groupA->id,
                'court_ids' => [$c1->id, $c2->id],
            ]);

        $res->assertOk()
            ->assertJsonPath('data.0.type', 'group')
            ->assertJsonPath('data.0.matches.0.label', 'T1 vs T2');

        $this->assertEquals($c1->id, $m1->fresh()->court_id);
        $this->assertEquals($this->ref1->id, $m1->fresh()->referee_id);
        $this->assertEquals($c2->id, $m2->fresh()->court_id);
        $this->assertEquals($this->ref2->id, $m2->fresh()->referee_id);
        $this->assertEquals($c1->id, $m3->fresh()->court_id);

        // Auto assign
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/tournaments/{$tournament->id}/court-board/auto")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'group');

        // Get matches list for board
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/tournaments/{$tournament->id}/matches")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'group');
    }

    public function test_referee_merged_matches_claim_busy_and_reassign_lock(): void
    {
        [$tour1, $type1] = $this->makeTournament('Picki Cup · Đôi nam');
        [$tour2, $type2] = $this->makeTournament('Picki Cup · Đôi nữ');

        $c1_t1 = TournamentCourt::create(['tournament_id' => $tour1->id, 'name' => 'Sân 1', 'sort_order' => 1, 'referee_user_id' => $this->ref1->id]);
        $c1_t2 = TournamentCourt::create(['tournament_id' => $tour2->id, 'name' => 'Sân 1', 'sort_order' => 1, 'referee_user_id' => $this->ref2->id]);

        $t1 = $this->makeTeam($tour1, $this->player1, 'P1');
        $t2 = $this->makeTeam($tour1, $this->player2, 'P2');
        $t3 = $this->makeTeam($tour2, $this->player1, 'P1_tour2'); // Same player1!
        $t4 = $this->makeTeam($tour2, $this->player3, 'P3');

        $m1 = $this->makeMatch($type1, null, $t1, $t2, ['court_id' => $c1_t1->id, 'referee_id' => $this->ref1->id]);
        $m2 = $this->makeMatch($type2, null, $t3, $t4, ['court_id' => $c1_t2->id, 'referee_id' => $this->ref2->id]);

        // Upcoming DRAFT tournament should also be included in /referee/tournaments
        [$upcomingTour] = $this->makeTournament('Upcoming Cup');
        $upcomingTour->update([
            'status' => Tournament::DRAFT,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(4),
        ]);

        // List referee tournaments
        $this->actingAs($this->ref1, 'api')
            ->getJson('/api/referee/tournaments')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Merged matches merges "Sân 1" into 1 court chip
        $merged = $this->actingAs($this->ref1, 'api')
            ->getJson("/api/referee/matches?tournament_ids={$tour1->id},{$tour2->id}")
            ->assertOk();

        $this->assertCount(1, $merged->json('data.courts'));
        $this->assertEquals('Sân 1', $merged->json('data.courts.0.name'));

        // ref1 claims m1
        $this->actingAs($this->ref1, 'api')
            ->postJson("/api/matches/{$m1->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.live_status', 'playing');

        // Second claim gets 409 ALREADY_CLAIMED
        $this->actingAs($this->ref1, 'api')
            ->postJson("/api/matches/{$m1->id}/claim")
            ->assertStatus(409);

        // ref2 tries to claim m2 while player1 is busy in m1 -> 409 PLAYER_BUSY
        $this->actingAs($this->ref2, 'api')
            ->postJson("/api/matches/{$m2->id}/claim")
            ->assertStatus(409);

        // Admin reassigns m1 from ref1 to ref2
        $this->actingAs($this->admin, 'api')
            ->postJson("/api/matches/{$m1->id}/reassign", ['referee_user_id' => $this->ref2->id])
            ->assertOk()
            ->assertJsonPath('data.referee_id', $this->ref2->id);

        // Old referee (ref1) is blocked with 403 NOT_MATCH_REFEREE when updating score
        $this->actingAs($this->ref1, 'api')
            ->postJson("/api/matches/{$m1->id}/score/update", [
                'team1_score' => 5,
                'team2_score' => 3,
                'serving_team_id' => $t1->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('errors.error_code', 'NOT_MATCH_REFEREE');
    }

    public function test_referee_can_pull_waiting_match_to_own_court_and_free_match_works(): void
    {
        [$tournament, $type] = $this->makeTournament();
        $c1 = TournamentCourt::create(['tournament_id' => $tournament->id, 'name' => 'Sân 1', 'sort_order' => 1, 'referee_user_id' => $this->ref1->id]);
        $c2 = TournamentCourt::create(['tournament_id' => $tournament->id, 'name' => 'Sân 2', 'sort_order' => 2, 'referee_user_id' => $this->ref2->id]);

        $t1 = $this->makeTeam($tournament, $this->player1, 'T1');
        $t2 = $this->makeTeam($tournament, $this->player2, 'T2');
        $m = $this->makeMatch($type, null, $t1, $t2, ['court_id' => $c1->id, 'referee_id' => $this->ref1->id]);

        // Pure referee (ref2) cannot move match to c1 (not their court), but CAN pull to c2 (their court)
        $this->actingAs($this->ref2, 'api')
            ->postJson("/api/matches/{$m->id}/move", ['court_id' => $c1->id, 'to_top' => true])
            ->assertStatus(403);

        $this->actingAs($this->ref2, 'api')
            ->postJson("/api/matches/{$m->id}/move", ['court_id' => $c2->id, 'to_top' => true])
            ->assertOk()
            ->assertJsonPath('data.court_id', $c2->id)
            ->assertJsonPath('data.referee_id', $this->ref2->id);

        // Free match CRUD
        $free = $this->actingAs($this->player4, 'api')
            ->postJson('/api/free-matches', [
                'title' => 'Trận giao hữu',
                'rules' => ['points_to_win' => 11, 'win_by' => 2, 'sets' => 1],
                'team_a' => [['name' => 'A1'], ['name' => 'A2']],
                'team_b' => [['name' => 'B1'], ['name' => 'B2']],
            ]);

        $free->assertCreated();
        $freeId = $free->json('data.id');

        $this->actingAs($this->player4, 'api')
            ->patchJson("/api/free-matches/{$freeId}", [
                'score' => ['sets' => [[11, 8]]],
                'status' => FreeMatch::STATUS_DONE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', FreeMatch::STATUS_DONE);
    }

    public function test_save_whole_court_board_and_auto_assign_preview(): void
    {
        [$tournament, $type] = $this->makeTournament();
        $c1 = TournamentCourt::create(['tournament_id' => $tournament->id, 'name' => 'Sân 1', 'sort_order' => 1]);

        $groupA = Group::create(['tournament_type_id' => $type->id, 'name' => 'A']);
        $t1 = $this->makeTeam($tournament, $this->player1, 'T1');
        $t2 = $this->makeTeam($tournament, $this->player2, 'T2');
        $m1 = $this->makeMatch($type, $groupA, $t1, $t2);
        $m2 = $this->makeMatch($type, $groupA, $t1, $t2);

        // Add 4 knockout matches (2 SF -> F, 1 F, 1 P3)
        $f = $this->makeMatch($type, null, $t1, $t2, ['round' => 3]);
        $sf1 = $this->makeMatch($type, null, $t1, $t2, ['round' => 2, 'next_match_id' => $f->id, 'next_position' => 1]);
        $sf2 = $this->makeMatch($type, null, $t1, $t2, ['round' => 2, 'next_match_id' => $f->id, 'next_position' => 2]);
        $p3 = $this->makeMatch($type, null, $t1, $t2, ['round' => 4, 'is_third_place' => true]);

        // 1. Auto assign preview with courts list
        $autoRes = $this->actingAs($this->admin, 'api')
            ->postJson("/api/tournaments/{$tournament->id}/court-board/auto", [
                'courts' => [
                    ['court_id' => $c1->id, 'number' => 1, 'name' => 'Sân 1'],
                    ['court_id' => null, 'number' => 2, 'name' => 'Sân 2'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.courts');

        $this->assertNotNull($autoRes->json('data.courts.0.referee_user_id'));
        $this->assertNotNull($autoRes->json('data.courts.1.referee_user_id'));
        $this->assertCount(2, $autoRes->json('data.courts.0.slots'));
        $this->assertCount(2, $autoRes->json('data.courts.1.slots'));
        $this->assertCount(3, $autoRes->json('data.courts.0.match_ids'));
        $this->assertCount(3, $autoRes->json('data.courts.1.match_ids'));

        // Preview should not persist Sân 2 yet
        $this->assertEquals(1, $tournament->courts()->count());

        // 2. Save whole board via PUT /court-board
        $saveRes = $this->actingAs($this->admin, 'api')
            ->putJson("/api/tournaments/{$tournament->id}/court-board", [
                'courts' => [
                    [
                        'court_id' => $c1->id,
                        'number' => 1,
                        'name' => 'Sân 1',
                        'referee_user_id' => $this->ref1->id,
                        'match_ids' => [$m1->id],
                        'slots' => [['round' => 'QF', 'no' => 1]],
                    ],
                    [
                        'court_id' => null,
                        'number' => 2,
                        'name' => 'Sân 2',
                        'referee_user_id' => $this->ref2->id,
                        'match_ids' => [$m2->id],
                        'slots' => [],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.courts');

        $this->assertEquals(2, $tournament->courts()->count());
        $this->assertEquals($c1->id, $m1->fresh()->court_id);
        $this->assertEquals($this->ref1->id, $m1->fresh()->referee_id);
        $this->assertEquals($this->ref2->id, $m2->fresh()->referee_id);
        $this->assertDatabaseHas('court_assignments', [
            'tournament_id' => $tournament->id,
            'unit_type' => CourtAssignment::UNIT_SLOT,
            'slot_round' => 'QF',
            'slot_no' => 1,
            'court_id' => $c1->id,
        ]);

        // Match detail returns court, court_id, court_name
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/matches/detail/{$m1->id}")
            ->assertOk()
            ->assertJsonPath('data.legs.0.court', 1)
            ->assertJsonPath('data.legs.0.court_id', $c1->id)
            ->assertJsonPath('data.legs.0.court_name', 'Sân 1');
    }

    public function test_event_edit_guard_allows_deleting_tournament_with_unplayed_matches_and_separates_message(): void
    {
        [$tournament, $type] = $this->makeTournament();
        $groupA = Group::create(['tournament_type_id' => $type->id, 'name' => 'A']);
        $t1 = $this->makeTeam($tournament, $this->player1, 'T1');
        $t2 = $this->makeTeam($tournament, $this->player2, 'T2');
        $m1 = $this->makeMatch($type, $groupA, $t1, $t2);

        $guard = app(\App\Services\EventEditGuardService::class);

        // Generated matches without results -> deletion is allowed
        $this->assertNull($guard->deletionBlockReason($tournament));

        // Once a match has a winner/result -> blocked with "Giải ..." message (not "Kèo/giải ...")
        $m1->update(['status' => Matches::STATUS_COMPLETED, 'winner_id' => $t1->id]);
        $freshGuard = new \App\Services\EventEditGuardService();
        $reason = $freshGuard->deletionBlockReason($tournament->fresh());
        $this->assertNotNull($reason);
        $this->assertStringStartsWith('Giải ', $reason);
        $this->assertStringNotContainsString('Kèo/giải', $reason);
    }
}
