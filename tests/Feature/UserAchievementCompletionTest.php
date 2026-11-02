<?php

namespace Tests\Feature;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Group;
use App\Models\Matches;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\Club\UserAchievementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests cho bug: "giải đã kết thúc nhưng chưa đá xong hết trận
 * vẫn được tính vào thành tích cup".
 *
 * Nguồn chân lý: CHỈ ghi `user_achievements` khi giải đã đá xong TẤT CẢ
 * trận (trận bye được miễn). `status = CLOSED` chỉ là "đóng đăng ký".
 */
class UserAchievementCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $creator = User::factory()->create();

        $this->club = Club::create([
            'name' => 'CLB Test',
            'status' => 'active',
            'is_verified' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function makeTournament(array $attrs = []): Tournament
    {
        return Tournament::create(array_merge([
            'name' => 'Giải Test',
            'sport_id' => 1,
            'start_date' => now()->subDay(),
            'duration' => 1440,
            'participant' => 'team',
            'club_id' => $this->club->id,
            'status' => Tournament::OPEN,
        ], $attrs));
    }

    private function makeType(Tournament $t): TournamentType
    {
        return TournamentType::create([
            'tournament_id' => $t->id,
            'format' => TournamentType::FORMAT_ELIMINATION,
            'match_rules' => '{}',
        ]);
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function makeTeams(Tournament $t, User ...$users): array
    {
        $teams = [];
        foreach ($users as $i => $user) {
            $team = Team::create([
                'name' => "Team {$user->id}",
                'tournament_id' => $t->id,
            ]);
            // belongsToMany()->create() sẽ insert vào bảng `users` — phải tạo
            // bảng pivot `team_members` trực tiếp.
            TeamMember::create(['team_id' => $team->id, 'user_id' => $user->id]);
            $teams[] = $team;
        }

        return [$teams[0], $teams[1]];
    }

    private function makeMatch(TournamentType $type, ?Team $home, ?Team $away, array $attrs = []): Matches
    {
        return Matches::create(array_merge([
            'tournament_type_id' => $type->id,
            'round' => 1,
            'home_team_id' => $home?->id,
            'away_team_id' => $away?->id,
            'status' => Matches::STATUS_PENDING,
            'is_bye' => false,
        ], $attrs));
    }

    private function joinClub(User $user, ?string $joinedAt = null): void
    {
        ClubMember::create([
            'user_id' => $user->id,
            'club_id' => $this->club->id,
            'joined_at' => $joinedAt ?? now()->subYear()->toDateTimeString(),
        ]);
    }

    /**
     * Giải CLOSED còn trận chưa đá → KHÔNG được ghi cup.
     */
    public function test_closed_tournament_with_pending_match_records_no_achievement(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        // Trận chưa đá.
        $this->makeMatch($type, $teamA, $teamB);

        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Giải còn trận chưa đá thì không được ghi cup.'
        );
    }

    /**
     * Giải CLOSED, mọi trận đã có winner → ĐƯỢC ghi cup.
     */
    public function test_closed_tournament_with_all_matches_completed_records_achievement(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        $match = $this->makeMatch($type, $teamA, $teamB);
        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);

        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Giải đã đá xong hết thì phải ghi cup.'
        );
    }

    /**
     * Trận bye không có winner_id nhưng KHÔNG phải trận chưa đá →
     * không được chặn việc ghi cup.
     */
    public function test_bye_match_does_not_block_achievement_recording(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        $match = $this->makeMatch($type, $teamA, $teamB);
        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);

        // Trận bye: winner_id = null.
        $this->makeMatch($type, $teamA, null, [
            'round' => 2,
            'is_bye' => true,
        ]);

        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Trận bye không được chặn việc ghi cup.'
        );
    }

    /**
     * hasAllMatchesCompleted() phải phản ánh đúng trạng thái thật.
     * Trước đây logic bị đảo ngược khiến giải chưa xong báo "đã xong".
     */
    public function test_has_all_matches_completed_reflects_reality(): void
    {
        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, User::factory()->create(), User::factory()->create());

        $match = $this->makeMatch($type, $teamA, $teamB);

        $this->assertFalse(
            $tournament->hasAllMatchesCompleted(),
            'Giải còn trận chưa đá thì chưa hoàn tất.'
        );

        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);
        $tournament->refresh();

        $this->assertTrue(
            $tournament->hasAllMatchesCompleted(),
            'Giải đá xong hết thì phải hoàn tất.'
        );
    }

    /**
     * Scope phải khớp với model method.
     */
    public function test_scope_matches_model_method(): void
    {
        $complete = $this->makeTournament();
        $completeType = $this->makeType($complete);
        [$a1, $b1] = $this->makeTeams($complete, User::factory()->create(), User::factory()->create());
        $m1 = $this->makeMatch($completeType, $a1, $b1);
        $m1->update(['winner_id' => $a1->id, 'status' => Matches::STATUS_COMPLETED]);

        $incomplete = $this->makeTournament();
        $incompleteType = $this->makeType($incomplete);
        [$a2, $b2] = $this->makeTeams($incomplete, User::factory()->create(), User::factory()->create());
        $this->makeMatch($incompleteType, $a2, $b2);

        $scopeIds = Tournament::allMatchesCompleted()->pluck('id')->sort()->values()->all();
        $modelIds = Tournament::all()->filter(fn ($t) => $t->hasAllMatchesCompleted())->pluck('id')->sort()->values()->all();

        $this->assertSame($modelIds, $scopeIds);
        $this->assertContains($complete->id, $scopeIds);
        $this->assertNotContains($incomplete->id, $scopeIds);
    }

    /**
     * Trận không có group (group_id = NULL) — 506 trận trong DB thuộc dạng này,
     * kể cả trận chung kết. Observer trước đây resolve tournament qua
     * `group` nên không chạy → cup bị mất vĩnh viễn.
     */
    public function test_match_without_group_still_records_achievement_when_tournament_already_closed(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        // group_id = NULL → quan hệ group không resolve được.
        $match = $this->makeMatch($type, $teamA, $teamB, ['group_id' => null]);

        // Giải đã đóng trước (auto-close theo end_date).
        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count()
        );

        // Trận cuối cùng được xác nhận → cup phải được ghi.
        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Trận không có group vẫn phải kích hoạt ghi cup.'
        );
    }

    /**
     * Command backfill phải gỡ cup của giải chưa xong và backfill giải đã xong.
     */
    public function test_backfill_command_purges_stale_and_backfills_missing(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        // Giải chưa xong → có achievement sai (giả lập trạng thái trước fix).
        $stale = $this->makeTournament();
        $staleType = $this->makeType($stale);
        [$s1, $s2] = $this->makeTeams($stale, $user, User::factory()->create());
        $this->makeMatch($staleType, $s1, $s2);
        $stale->updateQuietly(['status' => Tournament::CLOSED]);

        UserAchievement::create([
            'user_id' => $user->id,
            'club_id' => $this->club->id,
            'event_type' => UserAchievement::TYPE_TOURNAMENT,
            'event_id' => $stale->id,
            'event_name' => $stale->name,
            'event_date' => $stale->start_date,
            'event_status' => 'closed',
            'rank' => 1,
            'medal' => 'gold',
            'points' => 1,
            'is_cup' => true,
        ]);

        // Giải đã xong nhưng thiếu achievement — mô phỏng dữ liệu legacy.
        // Match xong → observer tự đóng giải + ghi cup; xoá cup đi để giả lập
        // trạng thái "đã đá xong nhưng chưa có cup".
        $good = $this->makeTournament();
        $goodType = $this->makeType($good);
        [$g1, $g2] = $this->makeTeams($good, $user, User::factory()->create());
        $m = $this->makeMatch($goodType, $g1, $g2);
        $m->update(['winner_id' => $g1->id, 'status' => Matches::STATUS_COMPLETED]);

        UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
            ->where('event_id', $good->id)
            ->delete();

        $this->assertSame(0, UserAchievement::where('event_id', $good->id)->count());

        $this->artisan('achievements:backfill')->assertSuccessful();

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $stale->id)
                ->count(),
            'Cup của giải chưa xong phải bị gỡ.'
        );

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $good->id)
                ->count(),
            'Cup của giải đã xong phải được backfill.'
        );
    }

    /**
     * Giải KHÔNG thuộc CLB (club_id = NULL) vẫn phải ghi cup cho CLB mà user
     * đã từng là member.
     *
     * Trước đây TournamentObserver có `if (!$clubId) return;` trước khi gọi
     * recorder → cup của mọi giải ngoài CLB bị mất vĩnh viễn.
     */
    public function test_tournament_without_club_still_records_achievement(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament(['club_id' => null]);
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        $match = $this->makeMatch($type, $teamA, $teamB);
        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);

        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->where('club_id', $this->club->id)
                ->count(),
            'Giải ngoài CLB vẫn phải ghi cup cho CLB của user.'
        );
    }

    /**
     * Kết quả bị hủy → giải không còn đá xong hết → cup phải bị gỡ.
     */
    public function test_achievement_is_purged_when_result_is_removed(): void
    {
        $user = User::factory()->create();
        $this->joinClub($user);

        $tournament = $this->makeTournament();
        $type = $this->makeType($tournament);
        [$teamA, $teamB] = $this->makeTeams($tournament, $user, User::factory()->create());

        $match = $this->makeMatch($type, $teamA, $teamB);
        $match->update(['winner_id' => $teamA->id, 'status' => Matches::STATUS_COMPLETED]);

        $tournament->update(['status' => Tournament::CLOSED]);

        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count()
        );

        // Hủy kết quả → trận chưa đá lại → cup phải bị rút.
        $match->update(['winner_id' => null, 'status' => Matches::STATUS_PENDING]);

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Hủy kết quả thì cup phải bị gỡ.'
        );
    }
}
