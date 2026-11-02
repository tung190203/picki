<?php

namespace Tests\Feature;

use App\Console\Commands\AutoCloseTournaments;
use App\Models\Group;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests cho bug "giải bị đóng sớm".
 *
 * Bug 1 — end_date là DATE nên MySQL cắt mất phần giờ:
 *   `end_date < now()` chỉ đúng sau NỮA ĐÊM ngày kết thúc. Giải start
 *   2026-10-21 19:00 + 1440 phút (= 2026-10-22 19:00) bị auto-close lúc
 *   12:50 ngày 22 — sớm hơn thực tế 6 tiếng.
 *
 * Bug 2 — JOIN trùng alias 'u' trong query xếp hạng làm closeTournament()
 *   throw sau khi đã update status => event TournamentCompleted không bắn,
 *   rating/achievement không được ghi.
 */
class TournamentAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tạo tournament tối giản. end_date được tính đúng công thức
     * start_date + duration (phút) — như calculateEndDate() đang làm.
     */
    private function makeTournament(array $attrs = []): Tournament
    {
        $startDate = $attrs['start_date'] ?? now()->subHour();

        return Tournament::create(array_merge([
            'name' => 'Test Giải Đấu',
            'sport_id' => 1,
            'start_date' => $startDate,
            'duration' => 1440,
            'participant' => 'team',
            'status' => Tournament::OPEN,
        ], $attrs));
    }

    /**
     * end_date phải giữ được phần giờ: start 19:00 + 1 ngày = 19:00 hôm sau.
     */
    public function test_end_date_keeps_time_component_not_truncated_to_date(): void
    {
        $tournament = $this->makeTournament([
            'start_date' => '2026-10-21 19:00:00',
            'duration' => 1440,
        ]);

        $endDate = \Illuminate\Support\Carbon::parse($tournament->start_date)
            ->addMinutes($tournament->duration);

        // Cột phải là DATETIME, không phải DATE.
        $this->assertSame(
            'datetime',
            \Illuminate\Support\Facades\Schema::getColumnType('tournaments', 'end_date')
        );

        // Khi lưu qua Eloquent, giá trị phải còn phần giờ.
        $tournament->forceFill(['end_date' => $endDate])->save();

        $this->assertSame(
            '2026-10-22 19:00:00',
            $tournament->fresh()->getRawOriginal('end_date')
        );
    }

    /**
     * Giải còn trong thời lượng (1 ngày, kết thúc 19:00 hôm sau) thì
     * auto-close KHÔNG được đóng, dù end_date đã qua nửa đêm.
     */
    public function test_auto_close_does_not_close_tournament_before_duration_ends(): void
    {
        // Bắt đầu 19:00 hôm qua, kết thúc 19:00 hôm nay → vẫn đang diễn ra.
        $tournament = $this->makeTournament([
            'start_date' => now()->subHours(5)->startOfHour(),
            'duration' => 1440,
        ]);

        $endDate = \Illuminate\Support\Carbon::parse($tournament->start_date)
            ->addMinutes($tournament->duration);

        $tournament->forceFill(['end_date' => $endDate])->save();

        $this->assertTrue($endDate->isFuture(), 'end_date phải còn ở tương lai');

        $this->artisan(AutoCloseTournaments::class)->assertSuccessful();

        $this->assertSame(
            Tournament::OPEN,
            $tournament->fresh()->status,
            'Giải chưa hết thời lượng thì không được đóng.'
        );
    }

    /**
     * Giải đã hết thời lượng thì auto-close phải đóng.
     */
    public function test_auto_close_closes_tournament_after_duration_ends(): void
    {
        $tournament = $this->makeTournament([
            'start_date' => now()->subDays(2),
            'duration' => 1440,
        ]);

        $endDate = \Illuminate\Support\Carbon::parse($tournament->start_date)
            ->addMinutes($tournament->duration);

        $tournament->forceFill(['end_date' => $endDate])->save();

        $this->artisan(AutoCloseTournaments::class)->assertSuccessful();

        $this->assertSame(
            Tournament::CLOSED,
            $tournament->fresh()->status
        );
    }

    /**
     * closeTournament() phải chạy trọn vẹn: query xếp hạng trước đây có
     * JOIN trùng alias 'u' -> SQL error 1066 làm throw sau khi đã update
     * status, khiến TournamentCompleted không bắn.
     */
    public function test_close_tournament_does_not_throw_on_ranking_query(): void
    {
        $tournament = $this->makeTournament([
            'start_date' => now()->subDays(2),
            'duration' => 1440,
        ]);

        $endDate = \Illuminate\Support\Carbon::parse($tournament->start_date)
            ->addMinutes($tournament->duration);
        $tournament->forceFill(['end_date' => $endDate])->save();

        \Illuminate\Support\Facades\Event::fake([
            \App\Events\TournamentCompleted::class,
        ]);

        // Không được throw.
        app(\App\Services\TournamentService::class)->closeTournament($tournament);

        $this->assertSame(Tournament::CLOSED, $tournament->fresh()->status);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\TournamentCompleted::class
        );
    }

    /**
     * Giải có nhiều tournament_type: chỉ đóng khi TẤT CẢ type đều xong.
     * Trước đây chỉ kiểm tra type đầu tiên nên giải bị đóng sớm.
     */
    public function test_tournament_with_multiple_types_closes_only_when_all_types_done(): void
    {
        $tournament = $this->makeTournament([
            'start_date' => now()->subDays(2),
            'duration' => 1440,
        ]);

        $type1 = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => 1,
            'match_rules' => '{}',
        ]);
        $type2 = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => 1,
            'match_rules' => '{}',
        ]);

        $group1 = Group::create(['tournament_type_id' => $type1->id, 'name' => 'G1']);
        $group2 = Group::create(['tournament_type_id' => $type2->id, 'name' => 'G2']);

        $teamA = \App\Models\Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        $teamB = \App\Models\Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        // Type 1: trận đã có kết quả.
        $match1 = Matches::create([
            'group_id' => $group1->id,
            'tournament_type_id' => $type1->id,
            'round' => 1,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status' => Matches::STATUS_COMPLETED,
            'winner_id' => $teamA->id,
        ]);

        // Type 2: trận chưa đá.
        $match2 = Matches::create([
            'group_id' => $group2->id,
            'tournament_type_id' => $type2->id,
            'round' => 1,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status' => Matches::STATUS_PENDING,
        ]);

        // Xác nhận kết quả match1 -> observer chạy, nhưng type2 còn trận chưa đá
        // => giải KHÔNG được đóng.
        $match1->update(['winner_id' => $teamA->id]);

        $this->assertSame(
            Tournament::OPEN,
            $tournament->fresh()->status,
            'Giải nhiều type chưa xong hết thì không được đóng.'
        );

        // Xác nhận nốt match2 -> lúc này mới đóng.
        $match2->update(['winner_id' => $teamB->id]);

        $this->assertSame(
            Tournament::CLOSED,
            $tournament->fresh()->status,
            'Tất cả type xong thì giải phải đóng.'
        );
    }

    /**
     * Trận bye không có winner_id và không phải trận chưa đá -> không được
     * chặn việc đóng giải.
     */
    public function test_bye_matches_do_not_block_tournament_closing(): void
    {
        $tournament = $this->makeTournament([
            'start_date' => now()->subDays(2),
            'duration' => 1440,
        ]);

        $type = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => 2,
            'match_rules' => '{}',
        ]);
        $group = Group::create(['tournament_type_id' => $type->id, 'name' => 'G1']);

        $teamA = \App\Models\Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        $teamB = \App\Models\Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        $realMatch = Matches::create([
            'group_id' => $group->id,
            'tournament_type_id' => $type->id,
            'round' => 1,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status' => Matches::STATUS_PENDING,
        ]);

        // Trận bye: có winner_id = null, is_bye = true.
        Matches::create([
            'group_id' => $group->id,
            'tournament_type_id' => $type->id,
            'round' => 2,
            'home_team_id' => $teamA->id,
            'away_team_id' => null,
            'status' => Matches::STATUS_PENDING,
            'is_bye' => true,
        ]);

        $realMatch->update(['winner_id' => $teamA->id]);

        $this->assertSame(
            Tournament::CLOSED,
            $tournament->fresh()->status,
            'Trận bye không được chặn việc đóng giải.'
        );
    }
}