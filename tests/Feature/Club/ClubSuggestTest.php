<?php

namespace Tests\Feature\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubSuggestTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL_LIMIT = 30;
    private const GROUP_LIMIT = 10;

    private function makeUser(): User
    {
        return User::factory()->create([
            'is_super_admin' => false,
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ]);
    }

    private function makeClub(array $overrides = []): Club
    {
        return Club::factory()->create(array_merge([
            'status' => ClubStatus::Active->value,
            'is_public' => true,
            'latitude' => 10.776,
            'longitude' => 106.701,
        ], $overrides));
    }

    private function makeMember(Club $club, User $user, string $role = 'admin'): ClubMember
    {
        return ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => $role,
            'membership_status' => ClubMembershipStatus::Joined->value,
            'status' => ClubMemberStatus::Active->value,
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

    private function addFriend(User $a, User $b): void
    {
        $now = now();
        DB::table('follows')->insert([
            ['user_id' => $a->id, 'followable_id' => $b->id, 'followable_type' => User::class, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $b->id, 'followable_id' => $a->id, 'followable_type' => User::class, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function setVndupr(User $user, float $score): void
    {
        $us = UserSport::create([
            'user_id' => $user->id,
            'sport_id' => Sport::PICKLEBALL_ID,
            'tier' => 'intermediate',
            'total_matches' => 0,
        ]);
        UserSportScore::create([
            'user_sport_id' => $us->id,
            'score_type' => 'vndupr_score',
            'score_value' => $score,
        ]);
    }

    /**
     * @return array<string, array<int, array>>
     */
    private function categoriesOf(array $data): array
    {
        $byCat = [];
        foreach ($data as $it) {
            $byCat[$it['category']][] = $it;
        }
        return $byCat;
    }

    public function test_friend_in_club_returns_friends_club_with_correct_label(): void
    {
        $viewer = $this->makeUser();
        $friend = $this->makeUser();
        $this->addFriend($viewer, $friend);

        $club = $this->makeClub(['name' => 'CLB Của Bạn']);
        $this->makeMember($club, $friend);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $cats = $this->categoriesOf($data);
        $this->assertArrayHasKey('friend_in_club', $cats);
        $this->assertCount(1, $cats['friend_in_club']);
        $this->assertSame($club->id, $cats['friend_in_club'][0]['id']);
        $this->assertSame('CLB có bạn bè của bạn', $cats['friend_in_club'][0]['category_text']);
    }

    public function test_following_returns_followed_club_with_correct_label(): void
    {
        $viewer = $this->makeUser();

        $club = $this->makeClub(['name' => 'CLB Tôi Theo Dõi']);
        $this->follow($viewer, $club);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $cats = $this->categoriesOf($data);
        $this->assertArrayHasKey('following', $cats);
        $this->assertCount(1, $cats['following']);
        $this->assertSame($club->id, $cats['following'][0]['id']);
        $this->assertSame('CLB bạn đang theo dõi', $cats['following'][0]['category_text']);
    }

    public function test_no_friends_no_follows_yields_empty_friend_and_following_groups(): void
    {
        $viewer = $this->makeUser();
        $this->makeUser();

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayNotHasKey('friend_in_club', $cats);
        $this->assertArrayNotHasKey('following', $cats);
    }

    public function test_suit_level_returns_club_with_matching_score_range(): void
    {
        $viewer = $this->makeUser();
        $this->setVndupr($viewer, 3.5);

        foreach ([3.0, 3.5, 4.0] as $score) {
            $member = $this->makeUser();
            $this->setVndupr($member, $score);
            $club = $this->makeClub(['name' => "CLB Score {$score}"]);
            $this->makeMember($club, $member);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayHasKey('suit_level', $cats);
        $this->assertGreaterThanOrEqual(1, count($cats['suit_level']));
        $this->assertSame('CLB hợp trình độ của bạn', $cats['suit_level'][0]['category_text']);
    }

    public function test_nearby_returns_club_within_radius(): void
    {
        $viewer = $this->makeUser();

        $club = $this->makeClub([
            'name' => 'CLB Gần',
            'latitude' => 10.780,
            'longitude' => 106.701,
        ]);

        $resp = $this->actingAs($viewer)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayHasKey('nearby', $cats);
        $this->assertCount(1, $cats['nearby']);
        $this->assertSame($club->id, $cats['nearby'][0]['id']);
        $this->assertSame('CLB gần bạn', $cats['nearby'][0]['category_text']);
        $this->assertNotNull($cats['nearby'][0]['distance']);
        $this->assertLessThan(2.0, $cats['nearby'][0]['distance']);
    }

    public function test_club_in_multiple_groups_is_deduplicated(): void
    {
        $viewer = $this->makeUser();
        $this->setVndupr($viewer, 3.5);

        $friend = $this->makeUser();
        $this->addFriend($viewer, $friend);
        $this->setVndupr($friend, 3.5);

        $club = $this->makeClub(['name' => 'CLB Trùng']);
        $this->makeMember($club, $friend);

        $resp = $this->actingAs($viewer)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $ids = array_column($data, 'id');
        $this->assertCount(1, array_filter($ids, fn($id) => $id === $club->id));

        $cats = $this->categoriesOf($data);
        $this->assertArrayHasKey('friend_in_club', $cats);
        $this->assertSame($club->id, $cats['friend_in_club'][0]['id']);
        $this->assertArrayNotHasKey('suit_level', $cats);
        $this->assertArrayNotHasKey('nearby', $cats);
    }

    public function test_friend_in_club_caps_at_ten(): void
    {
        $viewer = $this->makeUser();

        for ($i = 0; $i < 15; $i++) {
            $friend = $this->makeUser();
            $this->addFriend($viewer, $friend);
            $club = $this->makeClub(['name' => "CLB F{$i}"]);
            $this->makeMember($club, $friend);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayHasKey('friend_in_club', $cats);
        $this->assertCount(self::GROUP_LIMIT, $cats['friend_in_club']);
    }

    public function test_total_caps_at_thirty(): void
    {
        $viewer = $this->makeUser();
        $this->setVndupr($viewer, 3.5);

        for ($i = 0; $i < 12; $i++) {
            $friend = $this->makeUser();
            $this->addFriend($viewer, $friend);
            $club = $this->makeClub(['name' => "F{$i}"]);
            $this->makeMember($club, $friend);
        }
        for ($i = 0; $i < 14; $i++) {
            $club = $this->makeClub(['name' => "L{$i}"]);
            $this->follow($viewer, $club);
        }
        for ($i = 0; $i < 14; $i++) {
            $member = $this->makeUser();
            $this->setVndupr($member, 3.5);
            $club = $this->makeClub(['name' => "S{$i}"]);
            $this->makeMember($club, $member);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $this->assertLessThanOrEqual(self::TOTAL_LIMIT, count($resp->json('data')));
    }

    public function test_user_without_vndupr_score_omits_suit_level(): void
    {
        $viewer = $this->makeUser();

        $member = $this->makeUser();
        $this->setVndupr($member, 3.5);
        $club = $this->makeClub(['name' => 'CLB Có Score']);
        $this->makeMember($club, $member);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayNotHasKey('suit_level', $cats);
    }

    public function test_user_without_location_omits_nearby(): void
    {
        $viewer = $this->makeUser();

        $this->makeClub(['name' => 'CLB A', 'latitude' => 10.776, 'longitude' => 106.701]);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayNotHasKey('nearby', $cats);
    }

    public function test_no_club_within_radius_yields_empty_nearby(): void
    {
        $viewer = $this->makeUser();

        $this->makeClub([
            'name' => 'CLB Xa',
            'latitude' => 21.028,
            'longitude' => 105.854,
        ]);

        $resp = $this->actingAs($viewer)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayNotHasKey('nearby', $cats);
    }

    public function test_user_score_too_far_from_any_club_omits_suit_level(): void
    {
        $viewer = $this->makeUser();
        $this->setVndupr($viewer, 5.0);

        $member = $this->makeUser();
        $this->setVndupr($member, 3.0);
        $club = $this->makeClub(['name' => 'CLB Trình Thấp']);
        $this->makeMember($club, $member);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $cats = $this->categoriesOf($resp->json('data'));

        $this->assertArrayNotHasKey('suit_level', $cats);
    }
}
