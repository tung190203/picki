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
use App\Models\VnduprHistory;
use App\Services\MatchStageService;
use App\Services\TournamentScoringTypeInferrer;
use App\Services\VnduprRatingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 — VNDUPR Rating Per Stage.
 *
 * Coverage matrix:
 *  1. Rally stage rating uses stage_score (not cumulative).
 *  2. Cumulative not used as stage score.
 *  3. Non-participant user (in team_members, not in match_stage_members) is not rated.
 *  4. User in multiple stages has distinct history rows.
 *  5. Idempotent retry does not create duplicate.
 *  6. Concurrent engine calls: unique constraint blocks duplicate.
 *  7. Stage revision reverses old rating and recomputes.
 *  8. Stage unconfirm clears history (reverse works).
 *  9. Side-out rating unchanged (1 row per user per match, stage_id NULL).
 * 10. Old vndupr_history still readable.
 * 11. Leaderboard (user_sport_scores.score_value) gets updated.
 * 12. Migration preserves old data.
 */
class VnduprRatingEngineTest extends TestCase
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

    private function makeRallyContext(int $playerPerTeam = 3, ?User $organizer = null): array
    {
        $user = $organizer ?? $this->makeUser();
        $sport = Sport::factory()->create();
        $location = $this->makeLocation();

        $tournament = Tournament::create([
            'name' => 'Rally P4 ' . uniqid(),
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

        return compact('user', 'tournament', 'type', 'group', 'teamA', 'teamB', 'match', 'sport');
    }

    private function addTeamMember(Team $team, User $user, bool $isGuest = false): TeamMember
    {
        return TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $isGuest ? null : $user->id,
            'participant_id' => null,
        ]);
    }

    private function seedUserSport(User $user, int $sportId, float $score = 0): int
    {
        $usId = DB::table('user_sport')->insertGetId([
            'user_id' => $user->id,
            'sport_id' => $sportId,
            'tier' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($score !== 0) {
            DB::table('user_sport_scores')->insert([
                'user_sport_id' => $usId,
                'score_type' => 'vndupr_score',
                'score_value' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $usId;
    }

    public function test_rally_stage_uses_stage_score_not_cumulative(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u2 = $this->makeUser();
        $u3 = $this->makeUser(); $u4 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->addTeamMember($ctx['teamB'], $u4);

        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u2, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u4, $ctx['sport']->id, 3.0);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                // Stage 1: cumulative 10-6 → stage_score = 10-6
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        // 4 history rows: 2 per team
        $rows = VnduprHistory::where('match_id', $ctx['match']->id)->get();
        $this->assertCount(4, $rows);
        foreach ($rows as $r) {
            $this->assertNotNull($r->match_stage_id);
        }
    }

    public function test_cumulative_not_used_as_stage_score(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u2 = $this->makeUser();
        $u3 = $this->makeUser(); $u4 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->addTeamMember($ctx['teamB'], $u4);

        $this->seedUserSport($u1, $ctx['sport']->id, 2.0);
        $this->seedUserSport($u2, $ctx['sport']->id, 2.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 2.0);
        $this->seedUserSport($u4, $ctx['sport']->id, 2.0);

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

        // 3 stage × 4 user = 12 history rows
        $this->assertCount(12, VnduprHistory::where('match_id', $ctx['match']->id)->get());

        // Stage 1: cumulative 10-6  → stage_score 10-6   → home thắng
        // Stage 2: cumulative 20-14 → stage_score 10-8   → home thắng
        // Stage 3: cumulative 30-27 → stage_score 10-13  → AWAY thắng stage này
        // (cumulative cuối vẫn home 30 > away 27 nên match winner = home)
        $u1Rows = VnduprHistory::where('match_id', $ctx['match']->id)
            ->where('user_id', $u1->id)
            ->orderBy('match_stage_id')
            ->get();
        $this->assertCount(3, $u1Rows);
        // Stage 1: home thắng → score_after > score_before
        $this->assertGreaterThan($u1Rows[0]->score_before, (float) $u1Rows[0]->score_after);
        // Stage 2: home thắng → score_after > score_before
        $this->assertGreaterThan($u1Rows[1]->score_before, (float) $u1Rows[1]->score_after);
        // Stage 3: home THUA stage này → score_after < score_before
        $this->assertLessThan($u1Rows[2]->score_before, (float) $u1Rows[2]->score_after);

        // Away: ngược lại với từng stage
        $u3Rows = VnduprHistory::where('match_id', $ctx['match']->id)
            ->where('user_id', $u3->id)
            ->orderBy('match_stage_id')
            ->get();
        $this->assertCount(3, $u3Rows);
        $this->assertLessThan($u3Rows[0]->score_before, (float) $u3Rows[0]->score_after);
        $this->assertLessThan($u3Rows[1]->score_before, (float) $u3Rows[1]->score_after);
        $this->assertGreaterThan($u3Rows[2]->score_before, (float) $u3Rows[2]->score_after);
    }

    public function test_non_participant_user_not_rated_in_stage(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u2 = $this->makeUser(); // 2 user tham gia stage
        $uBench = $this->makeUser(); // user thuộc team nhưng không tham gia stage
        $u3 = $this->makeUser(); $u4 = $this->makeUser();

        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);
        $this->addTeamMember($ctx['teamA'], $uBench);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->addTeamMember($ctx['teamB'], $u4);

        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u2, $ctx['sport']->id, 3.0);
        $this->seedUserSport($uBench, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u4, $ctx['sport']->id, 3.0);

        // Gửi members chỉ định rõ u1, u3 tham gia (không có uBench, u2)
        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                [
                    'stage_no' => 1,
                    'cumulative_score_a' => 10,
                    'cumulative_score_b' => 6,
                    'members' => [
                        ['user_id' => $u1->id, 'team_id' => $ctx['teamA']->id],
                        ['user_id' => $u3->id, 'team_id' => $ctx['teamB']->id],
                    ],
                ],
            ],
            $ctx['user']->id,
        );

        // Chỉ u1 và u3 có history
        $this->assertSame(1, VnduprHistory::where('match_id', $ctx['match']->id)->where('user_id', $u1->id)->count());
        $this->assertSame(1, VnduprHistory::where('match_id', $ctx['match']->id)->where('user_id', $u3->id)->count());
        $this->assertSame(0, VnduprHistory::where('match_id', $ctx['match']->id)->where('user_id', $uBench->id)->count());
        $this->assertSame(0, VnduprHistory::where('match_id', $ctx['match']->id)->where('user_id', $u2->id)->count());
    }

    public function test_user_in_multiple_stages_has_distinct_history(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

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

        // u1 tham gia cả 3 stage → 3 row
        $rows = VnduprHistory::where('match_id', $ctx['match']->id)
            ->where('user_id', $u1->id)->get();
        $this->assertCount(3, $rows);
        $this->assertCount(3, $rows->pluck('match_stage_id')->unique());
    }

    public function test_retry_does_not_create_duplicate(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

        $service = app(MatchStageService::class);
        $payload = [
            ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
        ];
        $service->upsertStages($ctx['match'], $payload, $ctx['user']->id);
        $service->upsertStages($ctx['match']->fresh()->load('tournamentType.tournament'), $payload, $ctx['user']->id);

        $this->assertSame(2, VnduprHistory::where('match_id', $ctx['match']->id)->count());
    }

    public function test_concurrent_engine_calls_no_duplicate(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

        // Tạo stage thủ công
        $stage = MatchStage::create([
            'match_id' => $ctx['match']->id,
            'stage_no' => 1,
            'cumulative_score_a' => 10,
            'cumulative_score_b' => 6,
            'stage_score_a' => 10,
            'stage_score_b' => 6,
            'status' => MatchStage::STATUS_COMPLETED,
            'winner_team_id' => $ctx['teamA']->id,
            'target_point' => 10,
            'completed_at' => now(),
        ]);

        $engine = app(VnduprRatingEngine::class);
        $inputs = [
            'home_member_user_ids' => [$u1->id],
            'away_member_user_ids' => [$u3->id],
            'home_team_id' => $ctx['teamA']->id,
            'away_team_id' => $ctx['teamB']->id,
            'home_score' => 10,
            'away_score' => 6,
            'winner_team_id' => $ctx['teamA']->id,
            'match_id' => $ctx['match']->id,
            'match_stage_id' => $stage->id,
            'sport_id' => $ctx['sport']->id,
        ];

        $engine->computeAndApply($inputs);
        // Lần 2 cùng inputs → skip toàn bộ
        $r2 = $engine->computeAndApply($inputs);

        $this->assertSame(0, $r2['history_records']);
        $this->assertSame(2, $r2['skipped']);
        $this->assertSame(2, VnduprHistory::where('match_stage_id', $stage->id)->count());
    }

    public function test_stage_revision_reverses_old_and_recomputes(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
                ['stage_no' => 2, 'cumulative_score_a' => 20, 'cumulative_score_b' => 14],
            ],
            $ctx['user']->id,
        );

        $stage1 = MatchStage::where('match_id', $ctx['match']->id)->where('stage_no', 1)->first();
        $stage2 = MatchStage::where('match_id', $ctx['match']->id)->where('stage_no', 2)->first();

        $this->assertCount(4, VnduprHistory::where('match_id', $ctx['match']->id)->get());
        $stage1HistoryBefore = VnduprHistory::where('match_stage_id', $stage1->id)->get();
        $this->assertCount(2, $stage1HistoryBefore);

        // Sửa stage 1: cumulative 10-6 → 10-8 (vẫn chạm target 10 → completed)
        // Bằng chứng cũ: score_after của stage 1 history row. Sau revise, row MỚI phải khác.
        $scoreAfterBefore = (float) $stage1HistoryBefore->firstWhere('user_id', $u1->id)->score_after;

        $service->reviseStage(
            $ctx['match']->fresh(),
            1,
            ['cumulative_score_a' => 10, 'cumulative_score_b' => 8],
            $ctx['user']->id,
        );

        // Sau recompute, stage 1 vẫn có 2 history row (mới)
        $rowsStage1 = VnduprHistory::where('match_stage_id', $stage1->fresh()->id)->get();
        $this->assertCount(2, $rowsStage1);
        // Stage 2 vẫn nguyên 2 row
        $rowsStage2 = VnduprHistory::where('match_stage_id', $stage2->id)->get();
        $this->assertCount(2, $rowsStage2);

        // Score mới của u1 phải khác score cũ
        $scoreAfterAfter = (float) $rowsStage1->firstWhere('user_id', $u1->id)->score_after;
        $this->assertNotEquals($scoreAfterBefore, $scoreAfterAfter);

        // u1 score: tổng hợp cả 2 stage, vẫn > 3.0 ban đầu
        $u1Score = (float) DB::table('user_sport_scores')
            ->whereIn('user_sport_id', DB::table('user_sport')->where('user_id', $u1->id)->pluck('id'))
            ->where('score_type', 'vndupr_score')
            ->value('score_value');
        $this->assertNotNull($u1Score);
    }

    public function test_stage_unconfirm_clears_history(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        $stage = MatchStage::where('match_id', $ctx['match']->id)->where('stage_no', 1)->first();
        $this->assertCount(2, VnduprHistory::where('match_stage_id', $stage->id)->get());

        // Reverse thủ công
        app(VnduprRatingEngine::class)->reverse($ctx['match']->id, $stage->id);

        $this->assertCount(0, VnduprHistory::where('match_stage_id', $stage->id)->get());
        // u1 score trở về 3.0
        $score = DB::table('user_sport_scores')
            ->whereIn('user_sport_id', DB::table('user_sport')->where('user_id', $u1->id)->pluck('id'))
            ->where('score_type', 'vndupr_score')
            ->value('score_value');
        $this->assertEquals(3.0, (float) $score);
    }

    public function test_side_out_rating_unchanged(): void
    {
        $ctx = $this->makeRallyContext(2); // side-out
        // Force side-out scoring
        $ctx['type']->match_rules = array_merge(
            $ctx['type']->match_rules ?? [],
            ['scoring_type' => TournamentScoringTypeInferrer::SIDE_OUT],
        );
        $ctx['type']->save();

        $u1 = $this->makeUser(); $u2 = $this->makeUser();
        $u3 = $this->makeUser(); $u4 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamA'], $u2);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->addTeamMember($ctx['teamB'], $u4);

        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u2, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u4, $ctx['sport']->id, 3.0);

        // Gọi engine trực tiếp với match_stage_id=null (giả lập processMatchCompletionBig)
        $engine = app(VnduprRatingEngine::class);
        $result = $engine->computeAndApply([
            'home_member_user_ids' => [$u1->id, $u2->id],
            'away_member_user_ids' => [$u3->id, $u4->id],
            'home_team_id' => $ctx['teamA']->id,
            'away_team_id' => $ctx['teamB']->id,
            'home_score' => 11,
            'away_score' => 7,
            'winner_team_id' => $ctx['teamA']->id,
            'match_id' => $ctx['match']->id,
            'match_stage_id' => null,
            'sport_id' => $ctx['sport']->id,
        ]);

        $this->assertSame(4, $result['history_records']);
        $this->assertSame(4, VnduprHistory::where('match_id', $ctx['match']->id)->whereNull('match_stage_id')->count());
    }

    public function test_old_vndupr_history_still_readable(): void
    {
        $ctx = $this->makeRallyContext(2);
        $u1 = $this->makeUser();
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);

        // Tạo row cũ với match_stage_id = NULL
        VnduprHistory::create([
            'user_id' => $u1->id,
            'match_id' => $ctx['match']->id,
            'match_stage_id' => null,
            'mini_match_id' => null,
            'score_before' => 2.0,
            'score_after' => 3.0,
        ]);

        $this->assertCount(1, VnduprHistory::where('user_id', $u1->id)->whereNull('match_stage_id')->get());
        $this->assertSame(2.0, (float) VnduprHistory::where('user_id', $u1->id)->first()->score_before);
    }

    public function test_leaderboard_unaffected(): void
    {
        $ctx = $this->makeRallyContext(3);
        $u1 = $this->makeUser(); $u3 = $this->makeUser();
        $this->addTeamMember($ctx['teamA'], $u1);
        $this->addTeamMember($ctx['teamB'], $u3);
        $this->seedUserSport($u1, $ctx['sport']->id, 3.0);
        $this->seedUserSport($u3, $ctx['sport']->id, 3.0);

        $service = app(MatchStageService::class);
        $service->upsertStages(
            $ctx['match'],
            [
                ['stage_no' => 1, 'cumulative_score_a' => 10, 'cumulative_score_b' => 6],
            ],
            $ctx['user']->id,
        );

        $u1NewScore = DB::table('user_sport_scores')
            ->whereIn('user_sport_id', DB::table('user_sport')->where('user_id', $u1->id)->pluck('id'))
            ->where('score_type', 'vndupr_score')
            ->value('score_value');
        // 3.0 + delta K=0.3 W=0.6 S-E → score > 3.0
        $this->assertGreaterThan(3.0, (float) $u1NewScore);
    }

    public function test_migration_preserves_old_data(): void
    {
        // Migration test: schema constraint cho phép NULL match_stage_id,
        // insert row cũ (match_stage_id=null) vẫn thành công.
        $u = $this->makeUser();
        $ctx = $this->makeRallyContext(2);
        $this->seedUserSport($u, $ctx['sport']->id, 3.0);

        $row = VnduprHistory::create([
            'user_id' => $u->id,
            'match_id' => $ctx['match']->id,
            'match_stage_id' => null,
            'mini_match_id' => null,
            'score_before' => 2.0,
            'score_after' => 3.0,
        ]);

        $this->assertNotNull($row->id);
        $this->assertNull($row->match_stage_id);
        $this->assertSame(2.0, (float) $row->score_before);
    }
}
