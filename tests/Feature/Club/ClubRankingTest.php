<?php

namespace Tests\Feature\Club;

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

class ClubRankingTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeMember(Club $club, User $user): ClubMember
    {
        return ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'membership_status' => ClubMembershipStatus::Joined->value,
            'status' => ClubMemberStatus::Active->value,
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

    private function follow(User $user, Club $club, ?\Carbon\Carbon $when = null): void
    {
        // Use a unique offset (1s apart) so the order is stable regardless of clock.
        DB::table('follows')->insert([
            'user_id' => $user->id,
            'followable_id' => $club->id,
            'followable_type' => Club::class,
            'created_at' => $when ?? now(),
            'updated_at' => $when ?? now(),
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

    public function test_friend_in_club_ranked_by_friends_count_desc_then_verified(): void
    {
        $viewer = $this->makeUser();

        $f1 = $this->makeUser();
        $this->addFriend($viewer, $f1);
        $clubA = $this->makeClub(['name' => 'CLB A', 'is_verified' => false]);
        $this->makeMember($clubA, $f1);

        $friendsB = [$this->makeUser(), $this->makeUser(), $this->makeUser()];
        foreach ($friendsB as $f) {
            $this->addFriend($viewer, $f);
        }
        $clubB = $this->makeClub(['name' => 'CLB B', 'is_verified' => false]);
        foreach ($friendsB as $f) {
            $this->makeMember($clubB, $f);
        }

        $friendsC = [$this->makeUser(), $this->makeUser()];
        foreach ($friendsC as $f) {
            $this->addFriend($viewer, $f);
        }
        $clubC = $this->makeClub(['name' => 'CLB C', 'is_verified' => true]);
        foreach ($friendsC as $f) {
            $this->makeMember($clubC, $f);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $data = collect($resp->json('data'))->where('category', 'friend_in_club')->values()->all();

        $this->assertCount(3, $data);
        $this->assertSame($clubB->id, $data[0]['id'], '3 friends should rank first');
        $this->assertSame($clubC->id, $data[1]['id'], '2 friends + verified should rank second');
        $this->assertSame($clubA->id, $data[2]['id'], '1 friend + unverified should rank last');
    }

    public function test_following_ranked_by_follow_created_at_desc(): void
    {
        $viewer = $this->makeUser();

        // Use distinct timestamps far enough apart (days) that MySQL's second-precision
        // timestamp column cannot accidentally collapse them into a tie during fast test runs.
        $oldest = $this->makeClub(['name' => 'CLB Cũ']);
        $this->follow($viewer, $oldest, now()->subDays(10));

        $middle = $this->makeClub(['name' => 'CLB Giữa']);
        $this->follow($viewer, $middle, now()->subDays(5));

        $newest = $this->makeClub(['name' => 'CLB Mới']);
        $this->follow($viewer, $newest, now()->subHour());

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $data = collect($resp->json('data'))->where('category', 'following')->values()->all();

        $this->assertCount(3, $data);
        $this->assertSame($newest->id, $data[0]['id']);
        $this->assertSame($middle->id, $data[1]['id']);
        $this->assertSame($oldest->id, $data[2]['id']);
    }

    public function test_suit_level_ranked_by_midpoint_distance_asc(): void
    {
        $viewer = $this->makeUser();
        $this->setVndupr($viewer, 3.5);

        $buildClub = function (float $score) {
            $club = $this->makeClub(['name' => "ClubScore{$score}"]);
            $m1 = $this->makeUser();
            $this->setVndupr($m1, $score);
            $this->makeMember($club, $m1);
            return $club;
        };
        $club1 = $buildClub(3.0);
        $club2 = $buildClub(3.5);
        $club3 = $buildClub(4.0);

        $resp = $this->actingAs($viewer)->getJson('/api/clubs/suggest');
        $resp->assertOk();
        $data = collect($resp->json('data'))->where('category', 'suit_level')->values()->all();

        $this->assertCount(3, $data);
        $this->assertSame($club2->id, $data[0]['id'], 'Midpoint = user score ranks first');
        $rest = [$data[1]['id'], $data[2]['id']];
        $this->assertContains($club1->id, $rest);
        $this->assertContains($club3->id, $rest);
    }

    public function test_nearby_ranked_by_distance_asc(): void
    {
        $viewer = $this->makeUser();
        $near = $this->makeClub(['name' => 'Near',  'latitude' => 10.780, 'longitude' => 106.701]);
        $mid  = $this->makeClub(['name' => 'Mid',   'latitude' => 10.821, 'longitude' => 106.701]);
        $far  = $this->makeClub(['name' => 'Far',   'latitude' => 10.846, 'longitude' => 106.701]);

        $resp = $this->actingAs($viewer)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);
        $resp->assertOk();
        $data = collect($resp->json('data'))->where('category', 'nearby')->values()->all();

        $this->assertCount(3, $data);
        $this->assertSame($near->id, $data[0]['id']);
        $this->assertSame($mid->id,  $data[1]['id']);
        $this->assertSame($far->id,  $data[2]['id']);

        $this->assertLessThan($data[1]['distance'], $data[0]['distance']);
        $this->assertLessThan($data[2]['distance'], $data[1]['distance']);
    }
}
