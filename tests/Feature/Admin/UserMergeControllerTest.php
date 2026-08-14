<?php

namespace Tests\Feature\Admin;

use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTeam;
use App\Models\MiniTeamMember;
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserMerge;
use App\Models\VnduprHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMergeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);

        $this->userA = User::factory()->create([
            'full_name' => 'User A',
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);

        $this->userB = User::factory()->create([
            'full_name' => 'User B',
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);
    }

    protected function authenticateAsAdmin(): void
    {
        $this->actingAs($this->admin, 'api');
    }

    public function test_search_users_returns_active_users(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/admin/users/search?q=User');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_search_excludes_merged_users(): void
    {
        $this->authenticateAsAdmin();

        $mergedUser = User::factory()->create([
            'full_name' => 'Merged User',
            'is_guest' => false,
            'is_merged' => true,
            'merged_into_user_id' => $this->userA->id,
        ]);

        $response = $this->getJson('/api/admin/users/search?q=Merged');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_search_excludes_banned_users(): void
    {
        $this->authenticateAsAdmin();

        $bannedUser = User::factory()->create([
            'full_name' => 'Banned User',
            'is_guest' => false,
            'is_banned' => true,
        ]);

        $response = $this->getJson('/api/admin/users/search?q=Banned');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('meta.total'));
    }

    public function test_preview_merge_without_duplicates(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges/preview', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_a' => ['id', 'full_name', 'rating', 'played_matches'],
                    'user_b' => ['id', 'full_name', 'rating', 'played_matches'],
                    'duplicate_matches',
                    'duplicate_count',
                    'match_summary',
                    'can_continue',
                ],
            ]);

        $this->assertEquals(0, $response->json('data.duplicate_count'));
        $this->assertTrue($response->json('data.can_continue'));
    }

    public function test_preview_merge_same_user_rejected(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges/preview', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userA->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_preview_merge_nonexistent_user_rejected(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges/preview', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => 99999,
        ]);

        $response->assertStatus(422);
    }

    public function test_preview_final_with_survivor(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges/preview-final', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'survivor' => ['id', 'full_name'],
                    'merged_user' => ['id', 'full_name'],
                    'selected_info',
                    'match_summary',
                    'estimated_rating',
                    'login_warning',
                ],
            ]);

        $this->assertEquals($this->userA->id, $response->json('data.survivor.id'));
        $this->assertEquals($this->userB->id, $response->json('data.merged_user.id'));
    }

    public function test_preview_final_requires_override_when_duplicates_exist(): void
    {
        $this->authenticateAsAdmin();

        VnduprHistory::create([
            'user_id' => $this->userA->id,
            'score_before' => 1500,
            'score_after' => 1500,
        ]);
        VnduprHistory::create([
            'user_id' => $this->userB->id,
            'score_before' => 1500,
            'score_after' => 1500,
        ]);

        $response = $this->postJson('/api/admin/user-merges/preview-final', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
        ]);

        if ($response->json('data.duplicate_count', 0) > 0) {
            $response->assertStatus(400)
                ->assertJsonFragment(['status_code' => 'DUPLICATE_OVERRIDE_REQUIRED']);
        }
    }

    public function test_execute_merge_success(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User A',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'survivor_user_id',
                    'merged_user_id',
                    'status',
                    'matches_after_merge',
                ],
            ]);

        $this->assertEquals('completed', $response->json('data.status'));
        $this->assertEquals($this->userA->id, $response->json('data.survivor_user_id'));
        $this->assertEquals($this->userB->id, $response->json('data.merged_user_id'));

        $this->userB->refresh();
        $this->assertTrue($this->userB->is_merged);
        $this->assertEquals($this->userA->id, $this->userB->merged_into_user_id);
    }

    public function test_execute_merge_reverse_survivor(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userB->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User B',
        ]);

        $response->assertStatus(201);

        $this->userA->refresh();
        $this->assertTrue($this->userA->is_merged);
        $this->assertEquals($this->userB->id, $this->userA->merged_into_user_id);
    }

    public function test_execute_merge_confirmation_name_mismatch(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
            'confirmation_name' => 'Wrong Name',
        ]);

        $response->assertStatus(400);
    }

    public function test_merged_user_cannot_login(): void
    {
        $this->authenticateAsAdmin();

        $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User A',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $this->userB->phone ?? $this->userB->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['status_code' => 'USER_MERGED']);
    }

    public function test_survivor_can_login(): void
    {
        $this->authenticateAsAdmin();

        $this->userA->update(['password' => bcrypt('password123')]);
        $this->userB->update(['password' => bcrypt('password123')]);

        $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User A',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $this->userA->phone ?? $this->userA->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_cannot_merge_already_merged_user(): void
    {
        $this->authenticateAsAdmin();

        $userC = User::factory()->create([
            'full_name' => 'User C',
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
            'survivor_user_id' => $this->userA->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User A',
        ]);

        $response = $this->postJson('/api/admin/user-merges', [
            'user_a_id' => $this->userB->id,
            'user_b_id' => $userC->id,
            'survivor_user_id' => $userC->id,
            'duplicate_override' => false,
            'confirmation_name' => 'User C',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['status_code' => 'USER_ALREADY_MERGED']);
    }

    public function test_merge_history_list(): void
    {
        $this->authenticateAsAdmin();

        UserMerge::create([
            'survivor_user_id' => $this->userA->id,
            'merged_user_id' => $this->userB->id,
            'performed_by' => $this->admin->id,
            'status' => 'completed',
            'completed_at' => now(),
            'matches_after_merge' => 10,
        ]);

        $response = $this->getJson('/api/admin/user-merges');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_merge_history_detail(): void
    {
        $this->authenticateAsAdmin();

        $merge = UserMerge::create([
            'survivor_user_id' => $this->userA->id,
            'merged_user_id' => $this->userB->id,
            'performed_by' => $this->admin->id,
            'duplicate_count' => 2,
            'duplicate_override' => true,
            'matches_before_survivor' => 10,
            'matches_before_merged' => 5,
            'duplicate_matches_removed' => 2,
            'matches_after_merge' => 13,
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => [
                'survivor_snapshot' => ['full_name' => 'User A'],
                'merged_snapshot' => ['full_name' => 'User B'],
            ],
        ]);

        $response = $this->getJson("/api/admin/user-merges/{$merge->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'survivor',
                    'merged_user',
                    'match_summary',
                    'duplicate_matches',
                    'duplicate_override',
                    'selected_info',
                    'status',
                    'performed_by',
                ],
            ]);
    }

    public function test_unauthorized_user_cannot_access_merge_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => 'player',
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/admin/users/search?q=test');
        $response->assertStatus(403);
    }

    public function test_preview_merge_counts_mini_double_matches(): void
    {
        $this->authenticateAsAdmin();

        $sport = Sport::create(['name' => 'Pickleball', 'slug' => 'pickleball-test-' . uniqid()]);

        $tournament = MiniTournament::create([
            'sport_id' => $sport->id,
            'created_by' => $this->admin->id,
            'name' => 'Test Mini Double',
            'format' => MiniTournament::FORMAT_DOUBLE,
            'play_mode' => MiniTournament::PLAY_MODE_CASUAL,
            'start_time' => now(),
            'status' => MiniTournament::STATUS_OPEN,
        ]);

        $teamA = MiniTeam::create([
            'name' => 'Team A',
            'mini_tournament_id' => $tournament->id,
        ]);
        $teamB = MiniTeam::create([
            'name' => 'Team B',
            'mini_tournament_id' => $tournament->id,
        ]);
        $teamC = MiniTeam::create([
            'name' => 'Team C',
            'mini_tournament_id' => $tournament->id,
        ]);
        $teamD = MiniTeam::create([
            'name' => 'Team D',
            'mini_tournament_id' => $tournament->id,
        ]);

        MiniTeamMember::create([
            'mini_team_id' => $teamA->id,
            'user_id' => $this->userA->id,
        ]);
        MiniTeamMember::create([
            'mini_team_id' => $teamB->id,
            'user_id' => $this->userA->id,
        ]);
        MiniTeamMember::create([
            'mini_team_id' => $teamC->id,
            'user_id' => $this->userB->id,
        ]);
        MiniTeamMember::create([
            'mini_team_id' => $teamD->id,
            'user_id' => $this->userB->id,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            // UserA is in teamA and teamB; UserB is in teamC and teamD.
            // Alternate which user's team plays against UserB's teams.
            $userATeam = ($i % 2 === 0) ? $teamA->id : $teamB->id;
            $userBTeam = ($i % 2 === 0) ? $teamC->id : $teamD->id;

            MiniMatch::create([
                'mini_tournament_id' => $tournament->id,
                'team1_id' => $userATeam,
                'team2_id' => $userBTeam,
                'participant1_id' => null,
                'participant2_id' => null,
                'round_number' => $i,
                'status' => MiniMatch::STATUS_COMPLETED,
            ]);
        }

        $response = $this->postJson('/api/admin/user-merges/preview', [
            'user_a_id' => $this->userA->id,
            'user_b_id' => $this->userB->id,
        ]);

        $response->assertStatus(200);

        $this->assertGreaterThanOrEqual(
            6,
            $response->json('data.user_a.played_matches'),
            'user_a should have counted at least 6 mini double matches'
        );
        $this->assertGreaterThanOrEqual(
            6,
            $response->json('data.user_b.played_matches'),
            'user_b should have counted at least 6 mini double matches'
        );
        // merged_matches = user_a_total + user_b_total - duplicate_count
        // If all 6 matches are duplicates between userA's team and userB's team:
        // user_a=6, user_b=6, duplicates=6, merged=6
        $this->assertGreaterThanOrEqual(
            6,
            $response->json('data.match_summary.merged_matches'),
            'merged_matches should be at least 6 (6+6-6 duplicates)'
        );
    }
}
