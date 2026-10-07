<?php

namespace Tests\Feature\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Club\ClubRecurringSchedule;
use App\Models\CompetitionLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeCourtsAndSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $isSuperAdmin = false): User
    {
        return User::factory()->create([
            'is_super_admin' => $isSuperAdmin,
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ]);
    }

    private function makeClubWithManager(User $manager): Club
    {
        $club = Club::factory()->create([
            'recruitment_status' => 'closed',
        ]);
        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $manager->id,
            'role' => ClubMemberRole::Manager->value,
            'membership_status' => ClubMembershipStatus::Joined->value,
            'status' => ClubMemberStatus::Active->value,
        ]);
        return $club;
    }

    public function test_club_can_set_home_courts(): void
    {
        $manager = $this->makeUser();
        $club = $this->makeClubWithManager($manager);
        $loc1 = CompetitionLocation::factory()->create(['name' => 'Sân A']);
        $loc2 = CompetitionLocation::factory()->create(['name' => 'Sân B']);

        $response = $this->actingAs($manager)
            ->postJson("/api/clubs/{$club->id}/home-courts", [
                'locations' => [
                    ['competition_location_id' => $loc1->id, 'position' => 0, 'distance_km' => 1.2],
                    ['competition_location_id' => $loc2->id, 'position' => 1, 'distance_km' => 3.5],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertEquals(2, DB::table('club_competition_locations')->where('club_id', $club->id)->count());
    }

    public function test_non_manager_cannot_set_home_courts(): void
    {
        $user = $this->makeUser();
        $club = Club::factory()->create(['recruitment_status' => 'closed']);
        $loc = CompetitionLocation::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/clubs/{$club->id}/home-courts", [
                'locations' => [['competition_location_id' => $loc->id]],
            ]);

        $response->assertStatus(403);
        $this->assertEquals(0, DB::table('club_competition_locations')->where('club_id', $club->id)->count());
    }

    public function test_club_can_set_recurring_schedules(): void
    {
        $manager = $this->makeUser();
        $club = $this->makeClubWithManager($manager);

        $response = $this->actingAs($manager)
            ->postJson("/api/clubs/{$club->id}/recurring-schedules", [
                'day_of_week' => 1,
                'start_time' => '18:00',
                'end_time' => '21:00',
                'note' => 'Sân chính',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(1, ClubRecurringSchedule::where('club_id', $club->id)->count());

        // Thêm 1 dòng nữa
        $response2 = $this->actingAs($manager)
            ->postJson("/api/clubs/{$club->id}/recurring-schedules", [
                'day_of_week' => 3,
                'start_time' => '19:00',
                'end_time' => '22:00',
            ]);
        $response2->assertStatus(201);
        $this->assertEquals(2, ClubRecurringSchedule::where('club_id', $club->id)->count());
    }

    public function test_admin_can_toggle_recruitment_status(): void
    {
        $admin = $this->makeUser(isSuperAdmin: true);
        $club = Club::factory()->create(['recruitment_status' => 'closed']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/clubs/{$club->id}/recruitment-status", [
                'recruitment_status' => 'open',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('open', $club->fresh()->recruitment_status);
    }

    public function test_non_admin_cannot_toggle_recruitment_status(): void
    {
        $user = $this->makeUser(isSuperAdmin: false);
        $club = Club::factory()->create(['recruitment_status' => 'closed']);

        $response = $this->actingAs($user)
            ->postJson("/api/admin/clubs/{$club->id}/recruitment-status", [
                'recruitment_status' => 'open',
            ]);

        $response->assertStatus(403);
        $this->assertEquals('closed', $club->fresh()->recruitment_status);
    }

    public function test_club_detail_returns_new_fields(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClubWithManager($user);
        $loc = CompetitionLocation::factory()->create(['name' => 'Sân A']);

        DB::table('club_competition_locations')->insert([
            'club_id' => $club->id,
            'competition_location_id' => $loc->id,
            'position' => 0,
            'distance_km' => 2.5,
            'events_hosted_count' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ClubRecurringSchedule::create([
            'club_id' => $club->id,
            'day_of_week' => 2,
            'start_time' => '18:00',
            'end_time' => '21:00',
            'note' => 'Tập cơ bản',
            'position' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/clubs/{$club->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.recruitment_status', 'closed');
        $response->assertJsonPath('data.home_courts.0.name', 'Sân A');
        $response->assertJsonPath('data.home_courts.0.distance_km', 2.5);
        $response->assertJsonPath('data.home_courts.0.events_hosted_count', 3);
        $response->assertJsonPath('data.recurring_schedules.0.day_of_week', 2);
        $response->assertJsonPath('data.recurring_schedules.0.start_time', '18:00:00');
        $response->assertJsonPath('data.recurring_schedules.0.note', 'Tập cơ bản');
    }
}
