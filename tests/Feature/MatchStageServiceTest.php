<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\MatchStage;
use App\Models\MatchStageMember;
use App\Models\Sport;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentType;
use App\Models\User;
use App\Services\MatchStageService;
use App\Services\TournamentScoringTypeInferrer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 3 — Rally Scoring: core tests for MatchStageService.
 *
 * Coverage matrix:
 *  1. Save stage 1: cumulative + members + status
 *  2. Save stage 2: stage_score = delta
 *  3. Save stage 3: match.completed + winner
 *  4. Reject stage out of order
 *  5. Reject cumulative below previous
 *  6. Reject score exceeds target_point
 *  7. Reject update after match completed
 *  8. Idempotent retry
 *  9. Reject member not in team
 * 10. Does not use gender
 * 11. Side-out rejects saveStages
 * 12. Old match results still readable
 * 13. Group stage and knockout regression
 * 14. Transaction rollback on partial failure
 */
class MatchStageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ], $attrs));
    }

    private function makeLocation(): \App\Models\CompetitionLocation
    {
        return \App\Models\CompetitionLocation::create([
            'name' => 'Loc ' . uniqid(),
            'address' => '123 Test',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'is_banned' => false,
        ]);
    }

    private function makeRallyTournament(int $playerPerTeam = 3, ?User $user = null): array
    {
        $user = $user ?? $this->makeUser();
        $sport = Sport::factory()->create();
        $location = $this->makeLocation();

        $tournament = Tournament::create([
            'name' => 'Rally P3 ' . uniqid(),
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

        TournamentStaff::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
            'role' => TournamentStaff::ROLE_ORGANIZER,
        ]);

        $type = TournamentType::createWithFormat($tournament->id, TournamentType::FORMAT_MIXED, [
            'match_rules' => [
                'sets_per_match' => 1,
                'points_to_win_set' => 11,
                'scoring_type' => TournamentScoringTypeInferrer::RALLY,
                'stage_points' => [10, 20, 30],
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

        $group = Group::create(['tournament_type_id' => $type->id, 'name' => 'A']);

        $teamA = Team::create(['name' => 'Team A', 'tournament_id' => $tournament->id]);
        $teamB = Team::create(['name' => 'Team B', 'tournament_id' => $tournament->id]);

        $match = Matches::create([
            'tournament_type_id' => $type->id,
            'group_id' => $group->id,
            'round' => 1,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'leg' => 1,
            'status' => 'pending',
        ]);

        return compact('user', 'tournament', 'type', 'group', 'teamA', 'teamB', 'match');
    }

    private function addTeamMember(Team $team, User $user, bool $isGuest = false): TeamMember
    {
        return TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $isGuest ? null : $user->id,
            'participant_id' => null,
        ]);
    }

    public function test_save_stage1_rally_writes_cumulative_and_members(): void
    {
        $ctx = $this->makeRallyTournament(3);
        $u1 = $this->makeUser(); $u2 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);

        $service = app(MatchStageService::class);
        $saved = $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        $this->assertCount(1, $saved);
        $stage = $saved->first();
        $this->assertSame(1, $stage->stage_no);
        $this->assertSame(10, $stage->cumulative_score_a);
        $this->assertSame(6, $stage->cumulative_score_b);
        $this->assertSame(10, $stage->stage_score_a);
        $this->assertSame(6, $stage->stage_score_b);
        $this->assertSame(MatchStage::STATUS_COMPLETED, $stage->status);
        $this->assertSame(10, $stage->target_point);

        // Members lấy từ team_members mặc định
        $this->assertGreaterThanOrEqual(1, $stage->members()->where('team_id', $ctx['teamA']->id)->count());
    }

    public function test_save_stage2_computes_stage_score_from_delta(): void
    {
        $ctx = $this->makeRallyTournament(3);
        $u1 = $this->makeUser(); $u2 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        $saved = $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                ['stage_no' => 2, 'cumulative_score_a' => 20, 'cumulative_score_b' => 14],
            ],
            $ctx['user']->id,
        );

        $s2 = $saved->firstWhere('stage_no', 2);
        $this->assertNotNull($s2);
        $this->assertSame(10, $s2->stage_score_a); // 20 - 10
        $this->assertSame(8, $s2->stage_score_b);  // 14 - 6
        $this->assertSame(MatchStage::STATUS_COMPLETED, $s2->status);
    }

    public function test_save_stage3_completes_match_and_winner(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                ['stage_no' => 2, 'cumulative_score_a' => 20, 'cumulative_score_b' => 14],
                ['stage_no' => 3, 'cumulative_score_a' => 30, 'cumulative_score_b' => 27],
            ],
            $ctx['user']->id,
        );

        $match = $ctx['match']->fresh();
        $this->assertSame(Matches::STATUS_COMPLETED, $match->status);
        $this->assertSame($ctx['teamA']->id, $match->winner_id);
    }

    public function test_rejects_stage_out_of_order(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match'],
            [
                // Gửi stage 2 trước, không có stage 1
                ['stage_no' => 2, 'cumulative_score_a' => 20, 'cumulative_score_b' => 14],
            ],
            $ctx['user']->id,
        );
    }

    public function test_rejects_cumulative_below_previous(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                // Stage 2 có cumulative < stage 1
                ['stage_no' => 2, 'cumulative_score_a' => 5, 'cumulative_score_b' => 14],
            ],
            $ctx['user']->id,
        );
    }

    public function test_rejects_score_exceeds_target_point(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match'],
            [
                // stage 1 cumulative > target 10
                ['stage_no' => 1, 'cumulative_score_a' => 15, 'cumulative_score_b' => 5],
            ],
            $ctx['user']->id,
        );
    }

    public function test_rejects_update_after_match_completed(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                ['stage_no' => 2, 'cumulative_score_a' => 20, 'cumulative_score_b' => 14],
                ['stage_no' => 3, 'cumulative_score_a' => 30, 'cumulative_score_b' => 27],
            ],
            $ctx['user']->id,
        );

        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match']->fresh(),
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 5],
            ],
            $ctx['user']->id,
        );
    }

    public function test_idempotent_retry_same_payload(): void
    {
        $ctx = $this->makeRallyTournament(3);

        $service = app(MatchStageService::class);
        $payload = [
            ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
        ];

        $service->upsertStages($ctx['match'], $payload, $ctx['user']->id);
        $service->upsertStages($ctx['match']->fresh(), $payload, $ctx['user']->id);

        $this->assertSame(1, MatchStage::where('match_id', $ctx['match']->id)->count());
    }

    public function test_rejects_member_not_in_team(): void
    {
        $ctx = $this->makeRallyTournament(3);
        $u1 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);

        $service = app(MatchStageService::class);
        $stranger = $this->makeUser();

        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match'],
            [
                [
                    'stage_no' => 1,
                    'cumulative_score_a' => 10,
                    'cumulative_score_b' => 6,
                    'members' => [
                        ['user_id' => $stranger->id, 'team_id' => $ctx['teamA']->id],
                    ],
                ],
            ],
            $ctx['user']->id,
        );
    }

    public function test_does_not_use_gender(): void
    {
        $ctx = $this->makeRallyTournament(3);
        // users.gender là tinyint trong DB: dùng 0/1/2 (xem User model convention).
        $male = $this->makeUser(['gender' => 1]);
        $female = $this->makeUser(['gender' => 2]);
        $none = $this->makeUser(['gender' => 0]);

        $this->addTeamMember($ctx['teamA'], $male);
        $this->addTeamMember($ctx['teamA'], $female);
        $this->addTeamMember($ctx['teamA'], $none);

        $service = app(MatchStageService::class);
        $saved = $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        $stage = $saved->first();
        $memberCount = $stage->members()->where('team_id', $ctx['teamA']->id)->count();
        $this->assertSame(3, $memberCount);
    }

    public function test_side_out_rejects_save_stages(): void
    {
        $ctx = $this->makeRallyTournament(2); // player_per_team=2 → side_out
        // Force match_rules về side_out để service chắc chắn reject
        $ctx['type']->match_rules = array_merge(
            $ctx['type']->match_rules ?? [],
            ['scoring_type' => TournamentScoringTypeInferrer::SIDE_OUT],
        );
        $ctx['type']->save();

        $service = app(MatchStageService::class);
        $this->expectException(ValidationException::class);
        $service->upsertStages(
            $ctx['match']->fresh()->load('tournamentType.tournament'),
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );
    }

    public function test_old_match_results_still_readable(): void
    {
        $ctx = $this->makeRallyTournament(2); // side-out
        $ctx['type']->match_rules = array_merge(
            $ctx['type']->match_rules ?? [],
            ['scoring_type' => TournamentScoringTypeInferrer::SIDE_OUT],
        );
        $ctx['type']->save();

        // Tạo MatchResult cũ theo flow side-out
        MatchResult::create([
            'match_id' => $ctx['match']->id,
            'team_id' => $ctx['teamA']->id,
            'participant_id' => $ctx['teamA']->id,
            'score' => 11,
            'set_number' => 1,
            'won_match' => true,
        ]);

        $results = MatchResult::where('match_id', $ctx['match']->id)->get();
        $this->assertCount(1, $results);
        $this->assertSame(11, $results->first()->score);

        // MatchStage rỗng (side-out không ghi)
        $this->assertSame(0, MatchStage::where('match_id', $ctx['match']->id)->count());
    }

    public function test_group_stage_and_knockout_unaffected(): void
    {
        $ctx = $this->makeRallyTournament(3);

        // Group tồn tại, match vẫn pending
        $this->assertNotNull($ctx['group']);
        $this->assertSame('pending', $ctx['match']->status);

        // Lưu stage 1
        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        // Group vẫn còn, match chỉ cập nhật stages
        $this->assertNotNull($ctx['group']->fresh());
        $this->assertSame(Matches::STATUS_PENDING, $ctx['match']->fresh()->status);
    }

    public function test_transaction_rollback_on_partial_failure(): void
    {
        $ctx = $this->makeRallyTournament(3);
        $u1 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);

        $service = app(MatchStageService::class);

        // Lưu stage 1 OK trước
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );
        $this->assertSame(1, MatchStage::where('match_id', $ctx['match']->id)->count());

        // Lần 2: stage 1 OK, stage 2 sai (cumulative < prev) → throw → rollback
        try {
            $service->upsertStages(
                $ctx['match']->fresh(),
                [
                    ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                    ['stage_no' => 2, 'cumulative_score_a' => 5, 'cumulative_score_b' => 1],
                ],
                $ctx['user']->id,
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // Vẫn chỉ 1 stage (stage 1 không bị xóa, stage 2 không tạo)
            $this->assertSame(1, MatchStage::where('match_id', $ctx['match']->id)->count());
        }
    }
}
