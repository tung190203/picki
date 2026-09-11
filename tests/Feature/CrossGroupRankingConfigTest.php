<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\TournamentType\CrossGroupRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for cross_group_ranking config in format_specific_config.
 *
 * Test cases:
 * 1. MIXED 5+5+5+4 → applied=true, minimum_group_size=4, description contains rule
 * 2. MIXED 5+5+5+5 → applied=false, description does NOT contain rule
 * 3. MIXED 1 group 7 teams → applied=false
 * 4. FORMAT_ELIMINATION 5+4 → cross_group_ranking ignored, description unchanged
 * 5. Update from (5,5,4) to (5,5,5) → description strips rule
 * 6. Store 3 times consecutively → description has only 1 rule block
 */
class CrossGroupRankingConfigTest extends TestCase
{
    use RefreshDatabase;

    protected CrossGroupRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CrossGroupRankingService();
    }

    /**
     * Return auth headers for a user.
     */
    protected function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->accessToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Make an authenticated user.
     */
    protected function makeUser(): User
    {
        return User::factory()->create([
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // CrossGroupRankingService unit tests
    // -------------------------------------------------------------------------

    public function test_evaluate_enabled_true_mixed_uneven_groups(): void
    {
        $tournament = $this->makeTournamentWithMixedType(4, [5, 5, 5, 4]);

        $config = ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true];
        $result = $this->service->evaluate($tournament->tournamentTypes()->first(), $config);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['applied']);
        $this->assertEquals(4, $result['minimum_group_size']);
        $this->assertEquals([5, 5, 5, 4], $result['group_team_counts']);
        $this->assertEquals(4, $result['number_of_groups']);
        $this->assertFalse($result['is_group_counts_uniform']);
    }

    public function test_evaluate_enabled_true_mixed_even_groups(): void
    {
        $tournament = $this->makeTournamentWithMixedType(4, [5, 5, 5, 5]);

        $config = ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true];
        $result = $this->service->evaluate($tournament->tournamentTypes()->first(), $config);

        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['applied']);
        $this->assertNull($result['minimum_group_size']);
        $this->assertTrue($result['is_group_counts_uniform']);
    }

    public function test_evaluate_enabled_true_mixed_single_group(): void
    {
        $tournament = $this->makeTournamentWithMixedType(1, [7]);

        $config = ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true];
        $result = $this->service->evaluate($tournament->tournamentTypes()->first(), $config);

        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['applied']);
        $this->assertEquals(1, $result['number_of_groups']);
    }

    public function test_evaluate_enabled_false_mixed_even_groups(): void
    {
        $tournament = $this->makeTournamentWithMixedType(4, [5, 5, 5, 4]);

        $config = ['enabled' => false, 'apply_to' => ['runner_up'], 'exclude_bottom_team_matches' => true];
        $result = $this->service->evaluate($tournament->tournamentTypes()->first(), $config);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['applied']);
    }

    public function test_evaluate_non_mixed_format(): void
    {
        $tournament = $this->makeTournamentWithType(TournamentType::FORMAT_ELIMINATION);

        $config = ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true];
        $result = $this->service->evaluate($tournament->tournamentTypes()->first(), $config);

        $this->assertFalse($result['applied']);
    }

    public function test_normalize_config_uses_defaults(): void
    {
        $result = $this->service->normalizeConfig([]);
        $this->assertFalse($result['enabled']);
        $this->assertEquals(['runner_up', 'third_place'], $result['apply_to']);
        $this->assertTrue($result['exclude_bottom_team_matches']);
    }

    public function test_normalize_config_validates_apply_to(): void
    {
        $result = $this->service->normalizeConfig([
            'enabled' => true,
            'apply_to' => ['runner_up', 'invalid', 'third_place'],
            'exclude_bottom_team_matches' => false,
        ]);
        $this->assertEquals(['runner_up', 'third_place'], $result['apply_to']);
        $this->assertFalse($result['exclude_bottom_team_matches']);
    }

    public function test_normalize_config_coerces_string_booleans(): void
    {
        $result = $this->service->normalizeConfig([
            'enabled' => 'true',
            'exclude_bottom_team_matches' => '1',
        ]);
        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['exclude_bottom_team_matches']);
    }

    // -------------------------------------------------------------------------
    // Description sync tests
    // -------------------------------------------------------------------------

    public function test_sync_description_adds_rule_when_applied(): void
    {
        $tournament = $this->makeTournament();
        $evaluation = [
            'applied' => true,
            'minimum_group_size' => 4,
            'is_group_counts_uniform' => false,
        ];
        $rawConfig = ['enabled' => true];

        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $tournament->refresh();

        $this->assertStringContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
    }

    public function test_sync_description_strips_rule_when_not_applied(): void
    {
        $tournament = $this->makeTournament();
        $tournament->description = "Mô tả giải.\n\n" . CrossGroupRankingService::RULE_TEXT;
        $tournament->save();

        $evaluation = ['applied' => false, 'is_group_counts_uniform' => true];
        $rawConfig = ['enabled' => true];

        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $tournament->refresh();

        $this->assertStringNotContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
        $this->assertStringContainsString("Mô tả giải.", $tournament->description);
    }

    public function test_sync_description_strips_rule_when_disabled(): void
    {
        $tournament = $this->makeTournament();
        $tournament->description = "Mô tả giải.\n\n" . CrossGroupRankingService::RULE_TEXT;
        $tournament->save();

        $evaluation = ['applied' => true, 'is_group_counts_uniform' => false];
        $rawConfig = ['enabled' => false];

        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $tournament->refresh();

        $this->assertStringNotContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
    }

    public function test_sync_description_idempotent_no_duplicate(): void
    {
        $tournament = $this->makeTournament();
        $evaluation = [
            'applied' => true,
            'minimum_group_size' => 4,
            'is_group_counts_uniform' => false,
        ];
        $rawConfig = ['enabled' => true];

        // Call 3 times
        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $tournament->refresh();

        $count = substr_count($tournament->description, CrossGroupRankingService::RULE_BLOCK_MARKER);
        $this->assertEquals(1, $count);
    }

    public function test_sync_description_preserves_existing_user_content(): void
    {
        $tournament = $this->makeTournament();
        $tournament->description = "Đây là mô tả của BTC về giải đấu.\nGiải được tổ chức bởi CLB XYZ.";
        $tournament->save();

        $evaluation = [
            'applied' => true,
            'minimum_group_size' => 4,
            'is_group_counts_uniform' => false,
        ];
        $rawConfig = ['enabled' => true];

        $this->service->syncDescription($tournament, $evaluation, $rawConfig);
        $tournament->refresh();

        $this->assertStringContainsString("Đây là mô tả của BTC", $tournament->description);
        $this->assertStringContainsString("CLB XYZ", $tournament->description);
        $this->assertStringContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
    }

    // -------------------------------------------------------------------------
    // API endpoint tests
    // -------------------------------------------------------------------------

    public function test_store_mixed_5_5_5_4_syncs_description(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithMixedType(4, [5, 5, 5, 4], $user);

        $payload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => [
                'enabled' => true,
                'apply_to' => ['runner_up', 'third_place'],
                'exclude_bottom_team_matches' => true,
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);

        $tournament->refresh();
        $this->assertStringContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
    }

    public function test_store_mixed_5_5_5_5_does_not_add_description_rule(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithMixedType(4, [5, 5, 5, 5], $user);

        $payload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => [
                'enabled' => true,
                'apply_to' => ['runner_up', 'third_place'],
                'exclude_bottom_team_matches' => true,
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);

        $tournament->refresh();
        $this->assertStringNotContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description ?? ''
        );
    }

    public function test_store_elimination_does_not_apply_cross_group_ranking(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithType(TournamentType::FORMAT_ELIMINATION, $user);
        $originalDesc = 'Mô tả elimination giải đấu.';
        $tournament->description = $originalDesc;
        $tournament->save();

        $payload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => [
                'enabled' => true,
                'apply_to' => ['runner_up', 'third_place'],
                'exclude_bottom_team_matches' => true,
            ],
        ], TournamentType::FORMAT_ELIMINATION);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);

        $tournament->refresh();
        $this->assertEquals($originalDesc, $tournament->description);
    }

    public function test_store_mixed_single_group_does_not_add_rule(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithMixedType(1, [7], $user);

        $payload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => [
                'enabled' => true,
                'apply_to' => ['runner_up', 'third_place'],
                'exclude_bottom_team_matches' => true,
            ],
        ], TournamentType::FORMAT_MIXED, 1);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);

        $tournament->refresh();
        $this->assertStringNotContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description ?? ''
        );
    }

    public function test_update_strips_rule_when_groups_become_uniform(): void
    {
        $user = $this->makeUser();

        // Start with uneven groups (5,5,4) — rule should be added
        $tournament = $this->makeTournamentWithMixedType(3, [5, 5, 4], $user);
        $type = $tournament->tournamentTypes()->first();

        $storePayload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true],
        ], TournamentType::FORMAT_MIXED, 3);

        $this->actingAs($user)->postJson('/api/tournament-types/store', $storePayload);

        $tournament->refresh();
        $this->assertStringContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );

        // Update: reassign groups so all are 5 teams → rule should be stripped
        $this->reassignGroupsToUniform($type, 3, [5, 5, 5]);

        $updatePayload = $this->buildUpdatePayload($type, [
            'cross_group_ranking' => ['enabled' => true, 'apply_to' => ['runner_up', 'third_place'], 'exclude_bottom_team_matches' => true],
        ], TournamentType::FORMAT_MIXED, 3);

        $this->actingAs($user)->putJson("/api/tournament-types/{$type->id}", $updatePayload);

        $tournament->refresh();
        $this->assertStringNotContainsString(
            CrossGroupRankingService::RULE_BLOCK_MARKER,
            $tournament->description
        );
    }

    public function test_response_contains_cross_group_ranking_config(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithMixedType(2, [5, 4], $user);

        $payload = $this->buildStorePayload($tournament, [
            'cross_group_ranking' => [
                'enabled' => true,
                'apply_to' => ['runner_up', 'third_place'],
                'exclude_bottom_team_matches' => true,
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('data.format_specific_config.0.cross_group_ranking.enabled', true);
        $response->assertJsonPath('data.format_specific_config.0.cross_group_ranking.exclude_bottom_team_matches', true);
    }

    public function test_response_does_not_add_cross_group_ranking_when_not_sent(): void
    {
        $user = $this->makeUser();
        $tournament = $this->makeTournamentWithMixedType(2, [5, 4], $user);

        $payload = $this->buildStorePayload($tournament, []);

        $response = $this->actingAs($user)->postJson('/api/tournament-types/store', $payload);

        $response->assertStatus(200);
        // When not sent, the field should not appear (keeps payload clean for old clients)
        $this->assertArrayNotHasKey('cross_group_ranking', $response->json('data.format_specific_config.0'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create a minimal tournament for testing.
     */
    protected function makeTournament(?User $user = null): Tournament
    {
        $user = $user ?? $this->makeUser();
        $sport = Sport::factory()->create();

        return Tournament::create([
            'name' => 'Test Tournament',
            'sport_id' => $sport->id,
            'max_team' => 20,
            'player_per_team' => 2,
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'description' => '',
        ]);
    }

    /**
     * Create a tournament with a tournament type (no groups/teams set yet).
     */
    protected function makeTournamentWithType(int $format, ?User $user = null): Tournament
    {
        $tournament = $this->makeTournament($user);
        $type = TournamentType::createWithFormat($tournament->id, $format, [
            'format_specific_config' => [[
                'seeding_rules' => [1, 2, 3],
                'ranking' => [1, 4, 5, 2, 3],
            ]],
        ]);
        return $tournament;
    }

    /**
     * Create a tournament with a MIXED tournament type and teams distributed across groups.
     */
    protected function makeTournamentWithMixedType(int $numGroups, array $teamCountsPerGroup, ?User $user = null): Tournament
    {
        $totalTeams = array_sum($teamCountsPerGroup);
        $tournament = $this->makeTournament($user);
        $tournament->update(['max_team' => $totalTeams]);

        $type = TournamentType::createWithFormat($tournament->id, TournamentType::FORMAT_MIXED, [
            'format_specific_config' => [[
                'seeding_rules' => [1, 2, 3],
                'ranking' => [1, 4, 5, 2, 3],
                'pool_stage' => [
                    'name' => 'Vòng bảng',
                    'number_competing_teams' => $numGroups,
                    'num_advancing_teams' => 2,
                ],
                'has_third_place_match' => false,
                'has_resurrection_bracket' => false,
                'main_bracket_name' => 'Giải chính',
                'sub_bracket_name' => 'Giải Tái sinh',
            ]],
        ]);

        $this->assignTeamsToGroups($type, $teamCountsPerGroup);

        return $tournament;
    }

    /**
     * Assign teams to groups via the group_team pivot.
     * Creates teams and distributes them evenly across groups.
     */
    protected function assignTeamsToGroups(TournamentType $type, array $teamCountsPerGroup): void
    {
        $totalTeams = array_sum($teamCountsPerGroup);

        // Create teams
        $teams = [];
        for ($i = 0; $i < $totalTeams; $i++) {
            $team = Team::create([
                'name' => "Team " . ($i + 1),
                'tournament_id' => $type->tournament_id,
            ]);
            $teams[] = $team;
        }

        // Create groups and attach teams
        $teamIndex = 0;
        for ($i = 0; $i < count($teamCountsPerGroup); $i++) {
            $group = Group::create([
                'tournament_type_id' => $type->id,
                'name' => 'Bảng ' . chr(65 + $i),
            ]);

            $count = $teamCountsPerGroup[$i];
            for ($j = 0; $j < $count; $j++) {
                $group->teams()->attach($teams[$teamIndex++]->id, ['order' => $j + 1]);
            }
        }
    }

    /**
     * Reassign groups to have uniform team counts.
     */
    protected function reassignGroupsToUniform(TournamentType $type, int $numGroups, array $teamCountsPerGroup): void
    {
        // Detach all existing teams
        foreach ($type->groups as $group) {
            $group->teams()->detach();
        }

        $this->assignTeamsToGroups($type, $teamCountsPerGroup);
    }

    /**
     * Build the store payload for the tournament-types/store endpoint.
     */
    protected function buildStorePayload(
        Tournament $tournament,
        array $additionalConfig = [],
        int $format = TournamentType::FORMAT_MIXED,
        int $numGroups = 2
    ): array {
        $specificConfig = array_merge([
            'seeding_rules' => [1, 2, 3],
            'ranking' => [1, 4, 5, 2, 3],
            'has_third_place_match' => false,
            'advanced_to_next_round' => false,
            'pool_stage' => [
                'number_competing_teams' => $numGroups,
                'num_advancing_teams' => 2,
            ],
            'has_resurrection_bracket' => false,
            'main_bracket_name' => 'Giải chính',
            'sub_bracket_name' => 'Giải Tái sinh',
        ], $additionalConfig);

        return [
            'tournament_id' => $tournament->id,
            'format' => $format,
            'num_legs' => 1,
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
                'winning_rule' => 1,
                'max_points' => 11,
                'serve_change_interval' => null,
            ],
            'format_specific_config' => [$specificConfig],
        ];
    }

    /**
     * Regression test: when teams are pre-assigned to non-uniform groups with
     * cross_group_ranking.enabled (advanced_to_next_round=true) and num_advancing_teams=1,
     * round 2 should contain the correct number of teams (winners + best runner-ups to
     * reach next power-of-2), not just the real winners.
     *
     * Scenario: 5 groups with sizes [4, 4, 4, 4, 3] → nextPowerOf2(5) = 8.
     * Expected: 5 winners + 3 best runner-ups = 8 teams → 4 round-2 matches.
     *
     * @see PHASE 2.5 missing in generateMixedWithAssignedTeams
     */
    public function test_assign_teams_generates_correct_virtual_slots_for_non_uniform_groups(): void
    {
        $user = $this->makeUser();

        // Create a sport directly (no factory needed)
        $sport = Sport::create([
            'name' => 'Pickleball',
            'slug' => 'pickleball',
        ]);

        $tournament = Tournament::create([
            'name' => 'Regression: 19 teams in 5 groups',
            'sport_id' => $sport->id,
            'max_team' => 19,
            'player_per_team' => 2,
            'status' => Tournament::DRAFT,
            'created_by' => $user->id,
            'description' => '',
            'duration' => 1,
        ]);

        // Step 1: Create teams FIRST (store endpoint requires >= 2 teams in tournament).
        $teams = [];
        for ($i = 0; $i < 19; $i++) {
            $teams[] = Team::create([
                'name' => "Team " . ($i + 1),
                'tournament_id' => $tournament->id,
            ]);
        }

        // Step 2: Create tournament type with MIXED format and cross_group_ranking enabled.
        // advanced_to_next_round=true syncs to cross_group_ranking.enabled=true via store logic.
        // The store endpoint creates 5 empty groups (Bảng A..E) automatically.
        $storePayload = $this->buildStorePayload($tournament, [
            'advanced_to_next_round' => true,
            'pool_stage' => [
                'number_competing_teams' => 5,
                'num_advancing_teams' => 1,
            ],
            'has_third_place_match' => false,
            'has_resurrection_bracket' => false,
            'main_bracket_name' => 'Giải chính',
            'sub_bracket_name' => 'Giải Tái sinh',
        ], TournamentType::FORMAT_MIXED, 5);

        $this->actingAs($user)->postJson('/api/tournament-types/store', $storePayload);

        $type = $tournament->tournamentTypes()->first();
        $this->assertNotNull($type);
        $this->assertEquals(TournamentType::FORMAT_MIXED, $type->format);

        // Step 3: Attach teams to the 5 existing groups (created by store endpoint).
        // Groups: A=4, B=4, C=4, D=4, E=3 teams (total=19).
        $teamCounts = [4, 4, 4, 4, 3];
        $type->refresh();
        $groups = $type->groups->sortBy('id')->values(); // A, B, C, D, E in creation order

        // Detach any existing team attachments first
        foreach ($groups as $group) {
            $group->teams()->detach();
        }

        $teamIndex = 0;
        foreach ($groups as $group) {
            $count = $teamCounts[$teamIndex] ?? 3;
            for ($j = 0; $j < $count; $j++) {
                $group->teams()->attach($teams[$teamIndex++]->id, ['order' => $j + 1]);
            }
            // Verify after each group
            dump("After attaching to {$group->name}: {$group->teams()->count()} teams (teamIndex now at {$teamIndex})");
        }

        // Verify total team count across all groups
        $type->refresh();
        $totalTeamsInGroups = $type->groups->sum(function ($g) {
            return $g->teams()->count();
        });
        if ($totalTeamsInGroups !== 19) {
            // Debug: show group sizes
            foreach ($type->groups as $g) {
                dump("Group {$g->id} ({$g->name}): {$g->teams()->count()} teams");
            }
            dump('Total in groups:', $totalTeamsInGroups, 'Teams created:', count($teams));
        }
        $this->assertEquals(19, $totalTeamsInGroups, 'Should have 19 teams across all groups.');

        // Step 3: Call assign-teams-and-generate to trigger generateMixedWithAssignedTeams.
        $groupsPayload = $type->groups->map(function ($group) {
            return [
                'group_id' => $group->id,
                'team_ids' => $group->teams()->pluck('teams.id')->toArray(),
            ];
        })->values()->toArray();

        $response = $this->actingAs($user)->postJson(
            "/api/tournament-types/{$type->id}/assign-teams-and-generate",
            ['groups' => $groupsPayload]
        );

        $response->assertStatus(200);

        // Step 4: Verify round 2 has the correct number of matches.
        // With 5 groups × 1 advancing = 5 real winners.
        // nextPowerOf2(5) = 8 → 3 virtual Nhì tốt nhất needed.
        // Total: 5 + 3 = 8 teams → 4 round-2 matches.
        $type->refresh();
        $round2Matches = $type->matches()
            ->where('round', 2)
            ->where('bracket_type', 'main')
            ->get();

        $this->assertEquals(4, $round2Matches->count(), sprintf(
            'Expected 4 round-2 matches (8 slots) but got %d. ' .
            'PHASE 2.5 may be missing in generateMixedWithAssignedTeams.',
            $round2Matches->count()
        ));

        // Step 5: Verify the BYE/vacant slot pattern.
        // With 8 slots: 5 real teams + 3 virtual Nhì slots.
        // Each match has 1 real team + 1 virtual slot.
        $numLegs = (int) ($type->num_legs ?? 1);
        $matchGroups = $round2Matches->sortBy('id')->chunk($numLegs)->values();

        $hasRealTeam = 0;
        $hasVirtualSlot = 0;
        foreach ($matchGroups as $matchGroup) {
            $firstLeg = $matchGroup->first();
            if ($firstLeg->home_team_id !== null && $firstLeg->away_team_id !== null) {
                $hasRealTeam++;
            }
            if ($firstLeg->home_team_id === null || $firstLeg->away_team_id === null) {
                $hasVirtualSlot++;
            }
        }

        $this->assertEquals(5, $hasRealTeam, 'Should have 5 matches with a real team (one per group winner).');
        $this->assertEquals(3, $hasVirtualSlot, 'Should have 3 matches with a virtual Nhì tốt nhất slot.');
    }

    /**
     * Build the update payload for the tournament-types/{id} endpoint.
     */
    protected function buildUpdatePayload(
        TournamentType $type,
        array $additionalConfig = [],
        int $format = TournamentType::FORMAT_MIXED,
        int $numGroups = 2
    ): array {
        $specificConfig = array_merge([
            'seeding_rules' => [1, 2, 3],
            'ranking' => [1, 4, 5, 2, 3],
            'has_third_place_match' => false,
            'advanced_to_next_round' => false,
            'pool_stage' => [
                'number_competing_teams' => $numGroups,
                'num_advancing_teams' => 2,
            ],
            'has_resurrection_bracket' => false,
            'main_bracket_name' => 'Giải chính',
            'sub_bracket_name' => 'Giải Tái sinh',
        ], $additionalConfig);

        return [
            'num_legs' => 1,
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
                'winning_rule' => 1,
                'max_points' => 11,
                'serve_change_interval' => null,
            ],
            'format_specific_config' => [$specificConfig],
        ];
    }
}
