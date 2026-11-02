<?php

namespace Tests\Feature;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests cho kèo (mini-tournament):
 * - Kèo CHƯA đánh trận nào (0 trận) KHÔNG được ghi star.
 *   Trước đây `hasAllMatchesCompleted()` trả `true` (vacuous truth) → 28 kèo
 *   trong DB có gold/silver/bronze dù chưa đánh trận nào.
 * - Kèo chỉ có trận bye cũng không tính.
 * - Trận chưa có kết quả → không ghi star.
 * - Orphan row (event đã xoá) phải bị backfill gỡ.
 */
class MiniTournamentAchievementTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        // sports là FK bắt buộc của mini_tournaments.
        if (! \App\Models\Sport::find(1)) {
            \Illuminate\Support\Facades\DB::table('sports')->insert([
                'id' => 1,
                'name' => 'Pickleball',
                'slug' => 'pickleball',
            ]);
        }

        $creator = User::factory()->create();

        $this->club = Club::create([
            'name' => 'CLB Test',
            'status' => 'active',
            'is_verified' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function makeMini(array $attrs = []): MiniTournament
    {
        return MiniTournament::create(array_merge([
            'name' => 'Kèo Test',
            'sport_id' => 1,
            'club_id' => $this->club->id,
            'created_by' => $this->club->created_by,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now()->subDay(),
            'end_time' => now()->subHour(),
            'has_fee' => false,
        ], $attrs));
    }

    private function addParticipant(MiniTournament $mini, User $user, array $attrs = []): MiniParticipant
    {
        return MiniParticipant::create(array_merge([
            'mini_tournament_id' => $mini->id,
            'user_id' => $user->id,
            'is_confirmed' => true,
            'is_guest' => false,
            'is_absent' => false,
            'payment_status' => 'confirmed',
        ], $attrs));
    }

    private function addMatch(MiniTournament $mini, array $attrs = []): MiniMatch
    {
        return MiniMatch::create(array_merge([
            'mini_tournament_id' => $mini->id,
            'status' => MiniMatch::STATUS_COMPLETED,
            'is_bye' => false,
        ], $attrs));
    }

    /**
     * Kèo CLOSED mà CHƯA đánh trận nào → không được ghi star.
     *
     * Bug: `hasAllMatchesCompleted()` trả `true` vì không tìm thấy trận nào
     * chưa có kết quả → bảng xếp hạng chỉ là ngẫu nhiên.
     */
    public function test_closed_mini_with_zero_matches_records_no_achievement(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $this->addParticipant($mini, $user);

        $mini->update(['status' => MiniTournament::STATUS_CLOSED]);

        $this->assertFalse(
            $mini->hasAllMatchesCompleted(),
            'Kèo 0 trận thì không được coi là hoàn tất.'
        );

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count(),
            'Kèo chưa đánh trận nào thì không được ghi star.'
        );
    }

    /**
     * Kèo CLOSED chỉ có trận bye (không có đối thủ) → cũng không ghi star.
     */
    public function test_closed_mini_with_only_bye_matches_records_no_achievement(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $p = $this->addParticipant($mini, $user);

        $this->addMatch($mini, [
            'participant1_id' => $p->id,
            'is_bye' => true,
        ]);

        $mini->update(['status' => MiniTournament::STATUS_CLOSED]);

        $this->assertFalse(
            $mini->hasAllMatchesCompleted(),
            'Kèo chỉ có trận bye thì không được coi là hoàn tất.'
        );

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count(),
            'Kèo chỉ có trận bye thì không được ghi star.'
        );
    }

    /**
     * Kèo có trận thật nhưng chưa có kết quả → không ghi star.
     */
    public function test_closed_mini_with_pending_match_records_no_achievement(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $p1 = $this->addParticipant($mini, $user);
        $p2 = $this->addParticipant($mini, User::factory()->create());

        $this->addMatch($mini, [
            'participant1_id' => $p1->id,
            'participant2_id' => $p2->id,
            'status' => MiniMatch::STATUS_PENDING,
        ]);

        $mini->update(['status' => MiniTournament::STATUS_CLOSED]);

        $this->assertFalse($mini->hasAllMatchesCompleted());
        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count()
        );
    }

    /**
     * Kèo có trận thật đã có kết quả → ĐƯỢC ghi star.
     */
    public function test_closed_mini_with_completed_match_records_achievement(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $p1 = $this->addParticipant($mini, $user);
        $p2 = $this->addParticipant($mini, User::factory()->create());

        $this->addMatch($mini, [
            'participant1_id' => $p1->id,
            'participant2_id' => $p2->id,
            'participant_win_id' => $p1->id,
        ]);

        $mini->update(['status' => MiniTournament::STATUS_CLOSED]);

        $this->assertTrue($mini->hasAllMatchesCompleted());
        $this->assertGreaterThan(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count(),
            'Kèo đã đá xong hết thì phải ghi star.'
        );
    }

    /**
     * Scope phải khớp với model method (kèo 0 trận không được vào scope).
     */
    public function test_scope_matches_model_method_for_mini(): void
    {
        $zero = $this->makeMini();
        $this->addParticipant($zero, User::factory()->create());

        $complete = $this->makeMini();
        $p1 = $this->addParticipant($complete, User::factory()->create());
        $p2 = $this->addParticipant($complete, User::factory()->create());
        $this->addMatch($complete, [
            'participant1_id' => $p1->id,
            'participant2_id' => $p2->id,
            'participant_win_id' => $p1->id,
        ]);

        $scopeIds = MiniTournament::allMatchesCompleted()->pluck('id')->sort()->values()->all();
        $modelIds = MiniTournament::all()
            ->filter(fn ($m) => $m->hasAllMatchesCompleted())
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($modelIds, $scopeIds);
        $this->assertNotContains($zero->id, $scopeIds);
        $this->assertContains($complete->id, $scopeIds);
    }

    /**
     * Backfill phải gỡ star của kèo 0 trận (dữ liệu legacy trước fix).
     */
    public function test_backfill_purges_achievement_for_mini_with_zero_matches(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $this->addParticipant($mini, $user);
        $mini->updateQuietly(['status' => MiniTournament::STATUS_CLOSED]);

        // Giả lập row sai do bug 0 trận.
        UserAchievement::create([
            'user_id' => $user->id,
            'club_id' => $this->club->id,
            'event_type' => UserAchievement::TYPE_MINI_TOURNAMENT,
            'event_id' => $mini->id,
            'event_name' => $mini->name,
            'event_date' => $mini->start_time,
            'event_status' => 'closed',
            'rank' => 1,
            'medal' => 'gold',
            'points' => 3,
            'is_star' => true,
        ]);

        $this->artisan('achievements:backfill')->assertSuccessful();

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count(),
            'Star của kèo 0 trận phải bị gỡ.'
        );
    }

    /**
     * Backfill phải gỡ row trỏ tới kèo đã bị xoá (soft-delete).
     */
    public function test_backfill_purges_orphan_rows_for_deleted_mini(): void
    {
        $user = User::factory()->create();
        $mini = $this->makeMini();
        $this->addParticipant($mini, $user);
        $mini->updateQuietly(['status' => MiniTournament::STATUS_CLOSED]);

        UserAchievement::create([
            'user_id' => $user->id,
            'club_id' => $this->club->id,
            'event_type' => UserAchievement::TYPE_MINI_TOURNAMENT,
            'event_id' => $mini->id,
            'event_name' => $mini->name,
            'event_date' => $mini->start_time,
            'event_status' => 'closed',
            'rank' => 1,
            'medal' => 'gold',
            'points' => 3,
            'is_star' => true,
        ]);

        // Xoá mềm kèo → row thành orphan, vẫn hiện trên BXH CLB.
        $mini->delete();

        $this->artisan('achievements:backfill')->assertSuccessful();

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
                ->where('event_id', $mini->id)
                ->count(),
            'Row trỏ tới kèo đã xoá phải bị gỡ.'
        );
    }

    /**
     * Giải (tournament) 0 trận thật cũng không được ghi cup.
     */
    public function test_closed_tournament_with_zero_matches_records_no_achievement(): void
    {
        $user = User::factory()->create();
        ClubMember::create([
            'user_id' => $user->id,
            'club_id' => $this->club->id,
            'joined_at' => now()->subYear()->toDateTimeString(),
        ]);

        $tournament = \App\Models\Tournament::create([
            'name' => 'Giải 0 trận',
            'sport_id' => 1,
            'start_date' => now()->subDay(),
            'duration' => 1440,
            'participant' => 'team',
            'club_id' => $this->club->id,
            'status' => \App\Models\Tournament::OPEN,
        ]);

        $tournament->update(['status' => \App\Models\Tournament::CLOSED]);

        $this->assertFalse(
            $tournament->hasAllMatchesCompleted(),
            'Giải 0 trận thì không được coi là hoàn tất.'
        );

        $this->assertSame(
            0,
            UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
                ->where('event_id', $tournament->id)
                ->count(),
            'Giải chưa đánh trận nào thì không được ghi cup.'
        );
    }
}
