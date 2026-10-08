<?php

namespace Tests\Feature\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\CompetitionLocation;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lock in the SearchClubResource field contract on both the
 * search endpoint and the suggest endpoint.
 */
class ClubCardFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'is_super_admin' => false,
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ], $overrides));
    }

    private function makeClub(array $overrides = []): Club
    {
        $defaults = [
            'status' => ClubStatus::Active->value,
            'is_public' => true,
            'recruitment_status' => 'open',
            'address' => '123 Nguyễn Huệ, Q1, HCM',
            'latitude' => 10.776,
            'longitude' => 106.701,
        ];
        // If created_by not provided, fall back to a fresh user so the NOT NULL FK
        // on clubs.created_by never trips on an empty users table.
        if (!array_key_exists('created_by', $overrides)) {
            $defaults['created_by'] = $this->makeUser()->id;
        }
        return Club::factory()->create(array_merge($defaults, $overrides));
    }

    private function makeMember(Club $club, User $user, string $role): ClubMember
    {
        return ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => $role,
            'membership_status' => ClubMembershipStatus::Joined->value,
            'status' => ClubMemberStatus::Active->value,
        ]);
    }

    private function setVndupr(User $user, float $score, int $sportId = 1): void
    {
        $us = UserSport::create([
            'user_id' => $user->id,
            'sport_id' => $sportId,
            'tier' => 'intermediate',
            'total_matches' => 0,
        ]);
        UserSportScore::create([
            'user_sport_id' => $us->id,
            'score_type' => 'vndupr_score',
            'score_value' => $score,
        ]);
    }

    private function follow(User $user, Club $club): void
    {
        DB::table('follows')->insert([
            'user_id' => $user->id,
            'followable_id' => $club->id,
            'followable_type' => Club::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private const REQUIRED_TOP_FIELDS = [
        'id', 'name', 'address', 'latitude', 'longitude',
        'logo_url', 'status', 'is_verified', 'is_public', 'created_by',
        'quantity_members', 'is_admin', 'is_member', 'has_pending_request', 'has_invitation',
        'distance', 'marker_type',
        'active_matches_count', 'active_tournaments_count', 'announcements_count',
        'followers_count', 'is_following',
        'score_range', 'score_range_text',
        'recruitment_status', 'recruitment_status_text',
        'recurring_schedule_text',
        'primary_home_court',
        // Phase 2 fields
        'admin',
        'total_mini_tournaments_count',
        'total_tournaments_count',
    ];

    private function buildCardFixture(): array
    {
        $viewer = $this->makeUser();

        $leader = $this->makeUser();
        $leader->update(['full_name' => 'Nguyễn Lãnh Đạo']);
        $this->setVndupr($leader, 4.0);

        $verifiedClub = $this->makeClub([
            'name' => 'CLB Xác Minh',
            'is_verified' => true,
            'created_by' => $leader->id,
        ]);
        $this->makeMember($verifiedClub, $leader, ClubMemberRole::Admin->value);

        $unverifiedClub = $this->makeClub([
            'name' => 'CLB Thường',
            'is_verified' => false,
            'created_by' => $leader->id,
        ]);
        $this->makeMember($unverifiedClub, $leader, ClubMemberRole::Manager->value);

        $loc = CompetitionLocation::factory()->create([
            'name' => 'Sân Trung Tâm',
            'address' => '456 Lê Lợi, Q1, HCM',
            'latitude' => 10.775,
            'longitude' => 106.700,
        ]);
        DB::table('club_competition_locations')->insert([
            'club_id' => $verifiedClub->id,
            'competition_location_id' => $loc->id,
            'position' => 0,
            'distance_km' => 1.0,
            'events_hosted_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('club_competition_locations')->insert([
            'club_id' => $unverifiedClub->id,
            'competition_location_id' => $loc->id,
            'position' => 0,
            'distance_km' => 1.0,
            'events_hosted_count' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Make viewer follow the unverified one → is_following=true on that item
        $this->follow($viewer, $unverifiedClub);

        // Nearby club: ~0.5km from viewer (10.78, 106.701) so distance is populated
        $nearbyClub = $this->makeClub([
            'name' => 'CLB Gần Đây',
            'latitude' => 10.780,
            'longitude' => 106.701,
        ]);

        return [$viewer, $verifiedClub, $unverifiedClub, $loc, $nearbyClub];
    }

    public function test_search_endpoint_returns_all_required_card_fields(): void
    {
        [$viewer, $verifiedClub, $unverifiedClub, $loc, $nearbyClub] = $this->buildCardFixture();

        $resp = $this->actingAs($viewer)
            ->getJson('/api/search?tab=club&per_page=20&lat=10.776&lng=106.701');

        $resp->assertOk();
        // per_page wraps as { data: [...], meta: {...} }
        $items = $resp->json('data.data');
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(2, count($items));

        $union = collect($items)->reduce(fn($acc, $it) => $acc + array_flip(array_keys($it)), []);
        foreach (self::REQUIRED_TOP_FIELDS as $field) {
            $this->assertArrayHasKey($field, $union, "Search club item missing field: {$field}");
        }

        // Sanity: at least one item has populated admin
        $withAdmin = collect($items)->first(fn($it) => !empty($it['admin']));
        $this->assertNotNull($withAdmin, 'Expected at least one item with non-null admin');
        $this->assertArrayHasKey('full_name', $withAdmin['admin']);
        $this->assertArrayHasKey('avatar_url', $withAdmin['admin']);
        $this->assertArrayHasKey('vndupr_score', $withAdmin['admin']);

        // Sanity: at least one item has primary_home_court
        $withHome = collect($items)->first(fn($it) => !empty($it['primary_home_court']));
        $this->assertNotNull($withHome, 'Expected at least one item with primary_home_court');
        $this->assertArrayHasKey('id', $withHome['primary_home_court']);
        $this->assertArrayHasKey('name', $withHome['primary_home_court']);
        $this->assertArrayHasKey('address', $withHome['primary_home_court']);

        // Sanity: viewer follows the unverified club → is_following=true on that one
        $unverifiedItem = collect($items)->first(fn($it) => $it['id'] === $unverifiedClub->id);
        $this->assertNotNull($unverifiedItem);
        $this->assertTrue($unverifiedItem['is_following']);

        // Sanity: viewer is NOT following the verified club
        $verifiedItem = collect($items)->first(fn($it) => $it['id'] === $verifiedClub->id);
        $this->assertNotNull($verifiedItem);
        $this->assertFalse($verifiedItem['is_following']);

        // Sanity: is_verified carries through
        $this->assertTrue($verifiedItem['is_verified']);
        $this->assertFalse($unverifiedItem['is_verified']);
    }

    public function test_suggest_endpoint_returns_all_required_card_fields(): void
    {
        [$viewer, $verifiedClub, $unverifiedClub, $loc, $nearbyClub] = $this->buildCardFixture();

        // Make viewer a friend of the leader so the friend_in_club group populates.
        // Skip if viewer and leader are the same user (unique follow constraint).
        $leader = $verifiedClub->created_by; // creator
        if ($viewer->id !== $leader) {
            $now = now();
            foreach ([$viewer->id, $leader] as $uid) {
                $other = $uid === $viewer->id ? $leader : $viewer->id;
                DB::table('follows')->insert([
                    'user_id' => $uid,
                    'followable_id' => $other,
                    'followable_type' => User::class,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $resp = $this->actingAs($viewer)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);

        $resp->assertOk();
        $items = $resp->json('data');
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items), 'Expected at least 1 suggest item (friend_in_club)');

        // Each suggest item must carry category + category_text
        foreach ($items as $it) {
            $this->assertArrayHasKey('category', $it, 'suggest item missing category');
            $this->assertArrayHasKey('category_text', $it, 'suggest item missing category_text');
        }

        // Union of all top-level keys across the items
        $union = collect($items)->reduce(fn($acc, $it) => $acc + array_flip(array_keys($it)), []);
        foreach (self::REQUIRED_TOP_FIELDS as $field) {
            $this->assertArrayHasKey($field, $union, "Suggest item missing field: {$field}");
        }

        // Pin at least one category_text mapping
        $byCategory = collect($items)->groupBy('category');
        $this->assertArrayHasKey('friend_in_club', $byCategory->toArray(), 'Expected friend_in_club category present');
        $this->assertSame(
            'CLB có bạn bè của bạn',
            $byCategory['friend_in_club']->first()['category_text']
        );
    }

    public function test_search_club_returns_is_admin_when_viewer_is_club_admin(): void
    {
        $viewer = $this->makeUser();

        // Case 1: Admin via members role (Admin role, regardless of created_by value)
        $adminUser = $this->makeUser();
        $creator = $this->makeUser();
        $club = $this->makeClub(['name' => 'CLB Admin', 'created_by' => $creator->id]);
        $this->makeMember($club, $adminUser, ClubMemberRole::Admin->value);

        $resp = $this->actingAs($adminUser)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();
        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);
        $this->assertNotNull($item, 'Club should be in results');
        $this->assertTrue($item['is_admin'], 'Admin via members role should see is_admin=true');
        $this->assertTrue($item['is_member'], 'Admin should see is_member=true');

        // Case 2: Admin via created_by (creator is set and is Admin role)
        $creator = $this->makeUser();
        $club2 = $this->makeClub(['name' => 'CLB Creator', 'created_by' => $creator->id]);
        $this->makeMember($club2, $creator, ClubMemberRole::Admin->value);

        $resp2 = $this->actingAs($creator)->getJson('/api/search?tab=club&per_page=20');
        $resp2->assertOk();
        $items2 = $resp2->json('data.data');
        $item2 = collect($items2)->first(fn($it) => $it['id'] === $club2->id);
        $this->assertNotNull($item2, 'Club should be in results');
        $this->assertTrue($item2['is_admin'], 'Club creator should see is_admin=true');

        // Case 3: Non-admin member sees is_admin=false but is_member=true
        $member = $this->makeUser();
        $this->makeMember($club, $member, ClubMemberRole::Member->value);
        $resp3 = $this->actingAs($member)->getJson('/api/search?tab=club&per_page=20');
        $resp3->assertOk();
        $items3 = $resp3->json('data.data');
        $item3 = collect($items3)->first(fn($it) => $it['id'] === $club->id);
        $this->assertFalse($item3['is_admin'], 'Regular member should see is_admin=false');
        $this->assertTrue($item3['is_member'], 'Regular member should see is_member=true');

        // Case 4: map_mode also returns is_admin correctly
        $resp4 = $this->actingAs($adminUser)
            ->getJson('/api/search?tab=club&map_mode=true&per_page=20');
        $resp4->assertOk();
        $items4 = collect($resp4->json('data.data'));
        $item4 = $items4->first(fn($it) => $it['id'] === $club->id);
        $this->assertNotNull($item4, 'Club should be in map_mode results');
        $this->assertTrue($item4['is_admin'], 'map_mode: admin role should see is_admin=true');
        $this->assertTrue($item4['is_member'], 'map_mode: admin should see is_member=true');
    }

    public function test_admin_field_is_creator(): void
    {
        $creator = $this->makeUser(['full_name' => 'Người Tạo CLB']);

        $club = $this->makeClub([
            'name' => 'CLB Có Admin',
            'created_by' => $creator->id,
        ]);

        $viewer = $this->makeUser();
        $resp = $this->actingAs($viewer)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();

        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);

        // admin must be the creator (simple format)
        $this->assertNotNull($item['admin'], 'Admin should not be null when club has creator');
        $this->assertArrayHasKey('id', $item['admin']);
        $this->assertArrayHasKey('full_name', $item['admin']);
        $this->assertArrayHasKey('avatar_url', $item['admin']);
        $this->assertArrayHasKey('vndupr_score', $item['admin']);
        $this->assertSame($creator->id, $item['admin']['id']);
        $this->assertSame('Người Tạo CLB', $item['admin']['full_name']);
    }

    public function test_admin_is_null_when_creator_soft_deleted(): void
    {
        // Create a club with a creator, then soft-delete the creator
        $creator = $this->makeUser(['full_name' => 'Creator Will Be Deleted']);
        $club = $this->makeClub([
            'name' => 'CLB Creator Deleted',
            'created_by' => $creator->id,
        ]);

        // Soft delete the creator
        $creator->delete();

        $viewer = $this->makeUser();
        $resp = $this->actingAs($viewer)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();

        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);

        // admin should be null when creator user is soft-deleted
        $this->assertNull($item['admin'], 'Admin should be null when creator user is soft-deleted');
    }

    public function test_total_counts_include_all_history(): void
    {
        // Ensure sport exists (FK constraint on mini_tournaments.sport_id)
        if (!DB::table('sports')->where('id', 1)->exists()) {
            DB::table('sports')->insert([
                'id' => 1,
                'name' => 'Pickleball',
                'slug' => 'pickleball',
                'icon' => 'icon.png',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $creator = $this->makeUser();

        $club = $this->makeClub([
            'name' => 'CLB Nhiều Events',
            'created_by' => $creator->id,
        ]);

        // Create multiple mini tournaments (some active, some closed/cancelled)
        for ($i = 0; $i < 3; $i++) {
            DB::table('mini_tournaments')->insert([
                'name' => "Mini {$i}",
                'club_id' => $club->id,
                'created_by' => $creator->id,
                'sport_id' => 1,
                'status' => 2, // OPEN (active)
                'start_time' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        for ($i = 0; $i < 5; $i++) {
            DB::table('mini_tournaments')->insert([
                'name' => "Mini Closed {$i}",
                'club_id' => $club->id,
                'created_by' => $creator->id,
                'sport_id' => 1,
                'status' => 3, // CLOSED (not active)
                'start_time' => now()->subDays(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create tournaments (some active, some closed)
        for ($i = 0; $i < 2; $i++) {
            DB::table('tournaments')->insert([
                'name' => "Tour Active {$i}",
                'club_id' => $club->id,
                'created_by' => $creator->id,
                'sport_id' => 1,
                'status' => 1, // DRAFT or OPEN (active)
                'start_date' => now()->addMonth(),
                'duration' => 1, // required field
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        for ($i = 0; $i < 4; $i++) {
            DB::table('tournaments')->insert([
                'name' => "Tour Closed {$i}",
                'club_id' => $club->id,
                'created_by' => $creator->id,
                'sport_id' => 1,
                'status' => 3, // CLOSED (not active)
                'start_date' => now()->subMonths(2),
                'duration' => 1, // required field
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $viewer = $this->makeUser();
        $resp = $this->actingAs($viewer)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();

        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);

        // Total must include ALL history (active + closed)
        $this->assertSame(8, $item['total_mini_tournaments_count'], 'total_mini_tournaments_count should include all (3 active + 5 closed)');
        $this->assertSame(6, $item['total_tournaments_count'], 'total_tournaments_count should include all (2 active + 4 closed)');

        // Active counts should be lower (only active events)
        $this->assertSame(3, $item['active_matches_count'], 'active_matches_count should only include active mini tournaments');
        $this->assertSame(2, $item['active_tournaments_count'], 'active_tournaments_count should only include active tournaments');
    }

    public function test_total_counts_zero_when_no_events(): void
    {
        $creator = $this->makeUser();
        $club = $this->makeClub([
            'name' => 'CLB Mới',
            'created_by' => $creator->id,
        ]);

        $viewer = $this->makeUser();
        $resp = $this->actingAs($viewer)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();

        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);

        $this->assertSame(0, $item['total_mini_tournaments_count']);
        $this->assertSame(0, $item['total_tournaments_count']);
    }

    public function test_admin_field_format(): void
    {
        $creator = $this->makeUser([
            'full_name' => 'Admin Chính',
            'email' => 'admin@test.com',
        ]);

        $club = $this->makeClub(['created_by' => $creator->id]);

        $viewer = $this->makeUser();
        $resp = $this->actingAs($viewer)->getJson('/api/search?tab=club&per_page=20');
        $resp->assertOk();

        $items = $resp->json('data.data');
        $item = collect($items)->first(fn($it) => $it['id'] === $club->id);
        $admin = $item['admin'];

        // Admin simple format fields
        $this->assertArrayHasKey('id', $admin);
        $this->assertArrayHasKey('full_name', $admin);
        $this->assertArrayHasKey('avatar_url', $admin);
        $this->assertArrayHasKey('vndupr_score', $admin);
        $this->assertSame($creator->id, $admin['id']);
        $this->assertSame('Admin Chính', $admin['full_name']);
        // vndupr_score may be null since it's computed from user_sport_scores (not a direct column)
        $this->assertNull($admin['vndupr_score']);
    }
}
