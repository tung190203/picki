<?php

namespace Tests\Feature;

use App\Models\Club\Club;
use App\Models\Club\ClubGuestProfile;
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Admin\DashboardService;
use App\Services\Admin\UserManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchUserExcludesGuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_v2_user_tab_excludes_guest_users_and_matches_admin_count(): void
    {
        $realUser1 = User::factory()->create([
            'full_name' => 'Real Player One',
            'is_guest' => false,
        ]);
        $realUser2 = User::factory()->create([
            'full_name' => 'Real Player Two',
            'is_guest' => false,
        ]);
        $guestUser = User::factory()->create([
            'full_name' => 'Guest Player Virtual',
            'is_guest' => true,
        ]);

        $adminDashboardTotal = app(DashboardService::class)->getStats()['user_growth']['total'];
        $adminUsersTotal = app(UserManagementService::class)->search(1, 15, null, null)->total();

        $this->assertSame(2, $adminDashboardTotal);
        $this->assertSame(2, $adminUsersTotal);

        $response = $this->getJson('/api/search?tab=user');
        $response->assertOk();

        $metaTotal = $response->json('data.meta.total');
        $returnedIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame($adminDashboardTotal, $metaTotal);
        $this->assertContains($realUser1->id, $returnedIds);
        $this->assertContains($realUser2->id, $returnedIds);
        $this->assertNotContains($guestUser->id, $returnedIds);

        $playersResponse = $this->getJson('/api/players/search?tab=user');
        $playersResponse->assertOk();
        $this->assertSame($adminDashboardTotal, $playersResponse->json('data.meta.total'));
    }

    public function test_admin_search_excludes_guest_users_across_all_keyword_fields(): void
    {
        $realUser = User::factory()->create([
            'full_name' => 'Match Target',
            'email' => 'target-real@example.com',
            'is_guest' => false,
        ]);
        $guestByName = User::factory()->create([
            'full_name' => 'Match Target Guest',
            'email' => 'target-guest@example.com',
            'is_guest' => true,
        ]);

        $keyword = 'target';
        $userIds = User::where('is_guest', false)
            ->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            })
            ->pluck('id')
            ->all();

        $this->assertContains($realUser->id, $userIds);
        $this->assertNotContains($guestByName->id, $userIds);
    }

    public function test_virtual_members_only_returned_when_club_id_matches_organizing_club(): void
    {
        $sport = Sport::firstOrCreate(['slug' => 'pickleball'], ['name' => 'Pickleball']);

        $owner = User::factory()->create(['is_guest' => false]);
        $clubA = Club::create(['name' => 'CLB A', 'created_by' => $owner->id]);
        $clubB = Club::create(['name' => 'CLB B', 'created_by' => $owner->id]);

        $guestUser = User::factory()->create([
            'full_name' => 'Virtual Member A',
            'is_guest' => true,
        ]);
        ClubGuestProfile::create([
            'club_id' => $clubA->id,
            'user_id' => $guestUser->id,
            'display_name' => 'Virtual Member A',
        ]);

        $miniByClubA = MiniTournament::create([
            'name' => 'Kèo CLB A',
            'sport_id' => $sport->id,
            'club_id' => $clubA->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);
        $miniByClubB = MiniTournament::create([
            'name' => 'Kèo CLB B',
            'sport_id' => $sport->id,
            'club_id' => $clubB->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ]);

        $tourByClubA = Tournament::create([
            'name' => 'Giải CLB A',
            'sport_id' => $sport->id,
            'club_id' => $clubA->id,
            'start_date' => now()->addDay(),
            'duration' => 1440,
            'participant' => 'team',
        ]);
        $tourByClubB = Tournament::create([
            'name' => 'Giải CLB B',
            'sport_id' => $sport->id,
            'club_id' => $clubB->id,
            'start_date' => now()->addDay(),
            'duration' => 1440,
            'participant' => 'team',
        ]);

        // 1. Không truyền mini_tournament_id / tournament_id -> không trả user ảo
        $resNoEvent = $this->getJson("/api/search?tab=user&sub_tab=same_club&club_id={$clubA->id}");
        $resNoEvent->assertOk();
        $this->assertNotContains($guestUser->id, collect($resNoEvent->json('data.data'))->pluck('id')->all());

        // 2. mini_tournament_id của CLB A trùng club_id=A -> trả user ảo
        $resMiniMatch = $this->getJson("/api/search?tab=user&sub_tab=same_club&club_id={$clubA->id}&mini_tournament_id={$miniByClubA->id}");
        $resMiniMatch->assertOk();
        $this->assertContains($guestUser->id, collect($resMiniMatch->json('data.data'))->pluck('id')->all());

        // 3. mini_tournament_id của CLB B không trùng club_id=A -> không trả user ảo
        $resMiniMismatch = $this->getJson("/api/search?tab=user&sub_tab=same_club&club_id={$clubA->id}&mini_tournament_id={$miniByClubB->id}");
        $resMiniMismatch->assertOk();
        $this->assertNotContains($guestUser->id, collect($resMiniMismatch->json('data.data'))->pluck('id')->all());

        // 4. tournament_id của CLB A trùng club_id=A -> trả user ảo
        $resTourMatch = $this->getJson("/api/search?tab=user&sub_tab=same_club&club_id={$clubA->id}&tournament_id={$tourByClubA->id}");
        $resTourMatch->assertOk();
        $this->assertContains($guestUser->id, collect($resTourMatch->json('data.data'))->pluck('id')->all());

        // 5. tournament_id của CLB B không trùng club_id=A -> không trả user ảo
        $resTourMismatch = $this->getJson("/api/search?tab=user&sub_tab=same_club&club_id={$clubA->id}&tournament_id={$tourByClubB->id}");
        $resTourMismatch->assertOk();
        $this->assertNotContains($guestUser->id, collect($resTourMismatch->json('data.data'))->pluck('id')->all());
    }
}
