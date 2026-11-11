<?php

namespace Tests\Unit;

use App\Http\Resources\Admin\AdminCompetitionLocationResource;
use App\Http\Resources\Search\SearchCourtResource;
use App\Models\CompetitionLocation;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class SearchCourtActiveTournamentsTest extends TestCase
{
    public function test_active_tournaments_and_mini_tournaments_filters_upcoming_and_ongoing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00'));

        $location = (new CompetitionLocation())->forceFill([
            'id' => 1,
            'name' => 'Sân Cầu Lông ABC',
            'address' => '123 Đường 1',
        ]);

        $creator = (new User())->forceFill([
            'id' => 99,
            'full_name' => 'John Doe',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);

        // 1. Upcoming tournament (start in future)
        $upcomingTour = (new Tournament())->forceFill([
            'id' => 101,
            'name' => 'Giải Sắp Diễn Ra',
            'status' => Tournament::OPEN,
            'start_date' => '2026-11-15 08:00:00',
            'end_date' => '2026-11-17 18:00:00',
        ]);
        $upcomingTour->setRelation('creator', $creator);

        // 2. Ongoing tournament (started yesterday, ends tomorrow)
        $ongoingTour = (new Tournament())->forceFill([
            'id' => 102,
            'name' => 'Giải Đang Diễn Ra',
            'status' => Tournament::OPEN,
            'start_date' => '2026-11-10 08:00:00',
            'end_date' => '2026-11-12 18:00:00',
        ]);
        $ongoingTour->setRelation('creator', $creator);

        // 3. Past tournament (ended yesterday)
        $pastTour = (new Tournament())->forceFill([
            'id' => 103,
            'name' => 'Giải Đã Kết Thúc',
            'status' => Tournament::OPEN,
            'start_date' => '2026-11-08 08:00:00',
            'end_date' => '2026-11-10 18:00:00',
        ]);
        $pastTour->setRelation('creator', $creator);

        // 4. Closed tournament
        $closedTour = (new Tournament())->forceFill([
            'id' => 104,
            'name' => 'Giải Đã Đóng',
            'status' => Tournament::CLOSED,
            'start_date' => '2026-11-15 08:00:00',
            'end_date' => '2026-11-17 18:00:00',
        ]);

        // 5. Cancelled tournament
        $cancelledTour = (new Tournament())->forceFill([
            'id' => 105,
            'name' => 'Giải Đã Hủy',
            'status' => Tournament::CANCELLED,
            'start_date' => '2026-11-15 08:00:00',
            'end_date' => '2026-11-17 18:00:00',
        ]);

        // 6. Null end_date but old start_date (abandoned)
        $oldNullEndTour = (new Tournament())->forceFill([
            'id' => 106,
            'name' => 'Giải Cũ Không Có End Date',
            'status' => Tournament::OPEN,
            'start_date' => '2026-05-01 08:00:00',
            'end_date' => null,
        ]);

        // --- Mini Tournaments ---
        // 7. Upcoming mini tournament
        $upcomingMini = (new MiniTournament())->forceFill([
            'id' => 201,
            'name' => 'Kèo Sắp Diễn Ra',
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => Carbon::parse('2026-11-13 14:00:00'),
            'end_time' => Carbon::parse('2026-11-13 16:00:00'),
        ]);
        $upcomingMini->setRelation('creator', $creator);

        // 8. Ongoing mini tournament (started 1 hour ago, ends in 1 hour)
        $ongoingMini = (new MiniTournament())->forceFill([
            'id' => 202,
            'name' => 'Kèo Đang Diễn Ra',
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => Carbon::parse('2026-11-11 11:00:00'),
            'end_time' => Carbon::parse('2026-11-11 13:00:00'),
        ]);
        $ongoingMini->setRelation('creator', $creator);

        // 9. Past mini tournament (ended 1 hour ago)
        $pastMini = (new MiniTournament())->forceFill([
            'id' => 203,
            'name' => 'Kèo Đã Kết Thúc',
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => Carbon::parse('2026-11-11 09:00:00'),
            'end_time' => Carbon::parse('2026-11-11 11:00:00'),
        ]);

        // 10. Finished session status
        $finishedMini = (new MiniTournament())->forceFill([
            'id' => 204,
            'name' => 'Kèo Đã Xong Session',
            'status' => MiniTournament::STATUS_OPEN,
            'session_status' => MiniTournament::SESSION_STATUS_FINISHED,
            'start_time' => Carbon::parse('2026-11-11 11:00:00'),
            'end_time' => Carbon::parse('2026-11-11 13:00:00'),
        ]);

        // 11. Closed mini tournament
        $closedMini = (new MiniTournament())->forceFill([
            'id' => 205,
            'name' => 'Kèo Đã Đóng',
            'status' => MiniTournament::STATUS_CLOSED,
            'start_time' => Carbon::parse('2026-11-13 14:00:00'),
            'end_time' => Carbon::parse('2026-11-13 16:00:00'),
        ]);

        $location->setRelation('tournaments', collect([
            $upcomingTour,
            $ongoingTour,
            $pastTour,
            $closedTour,
            $cancelledTour,
            $oldNullEndTour,
        ]));

        $location->setRelation('miniTournaments', collect([
            $upcomingMini,
            $ongoingMini,
            $pastMini,
            $finishedMini,
            $closedMini,
        ]));

        $request = Request::create('/api/search', 'GET', ['tab' => 'court']);

        // Test SearchCourtResource
        $searchCourtResult = (new SearchCourtResource($location))->toArray($request);

        $this->assertSame(2, $searchCourtResult['active_tournaments_count']);
        $this->assertCount(2, $searchCourtResult['active_tournaments']);
        $this->assertSame([101, 102], collect($searchCourtResult['active_tournaments'])->pluck('id')->all());

        $this->assertSame(2, $searchCourtResult['active_matches_count']);
        $this->assertCount(2, $searchCourtResult['active_mini_tournaments']);
        $this->assertSame([201, 202], collect($searchCourtResult['active_mini_tournaments'])->pluck('id')->all());

        // Test AdminCompetitionLocationResource
        $adminResult = (new AdminCompetitionLocationResource($location))->toArray($request);

        $this->assertSame(2, $adminResult['active_tournaments_count']);
        $this->assertCount(2, $adminResult['active_tournaments']);
        $this->assertSame([101, 102], collect($adminResult['active_tournaments'])->pluck('id')->all());

        $this->assertSame(2, $adminResult['active_matches_count']);
        $this->assertCount(2, $adminResult['active_mini_tournaments']);
        $this->assertSame([201, 202], collect($adminResult['active_mini_tournaments'])->pluck('id')->all());

        // Check item structures
        $firstTour = $searchCourtResult['active_tournaments'][0];
        $this->assertSame(101, $firstTour['id']);
        $this->assertSame('Giải Sắp Diễn Ra', $firstTour['name']);
        $this->assertSame('tournament', $firstTour['type']);
        $this->assertSame(Tournament::OPEN, $firstTour['status']);
        $this->assertSame('2026-11-15', $firstTour['start_date']);
        $this->assertNotNull($firstTour['created_by']);

        $firstMini = $searchCourtResult['active_mini_tournaments'][0];
        $this->assertSame(201, $firstMini['id']);
        $this->assertSame('Kèo Sắp Diễn Ra', $firstMini['name']);
        $this->assertSame(MiniTournament::STATUS_OPEN, $firstMini['status']);
        $this->assertSame('2026-11-13', $firstMini['start_date']);
        $this->assertNotNull($firstMini['created_by']);

        // Test with un-loaded relations
        $emptyLocation = (new CompetitionLocation())->forceFill([
            'id' => 2,
            'name' => 'Sân Trống',
        ]);
        $emptySearchResult = (new SearchCourtResource($emptyLocation))->toArray($request);
        $this->assertSame(0, $emptySearchResult['active_matches_count']);
        $this->assertSame(0, $emptySearchResult['active_tournaments_count']);
        $this->assertCount(0, $emptySearchResult['active_tournaments']);
        $this->assertCount(0, $emptySearchResult['active_mini_tournaments']);

        Carbon::setTestNow();
    }
}
