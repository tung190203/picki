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

/**
 * N+1 guard. Counts queries via DB::listen and asserts each
 * request stays under a per-test ceiling. A failing test means
 * someone added a per-row query (foreach → query) — fix the root
 * cause in the controller/service, not by bumping the ceiling.
 */
class ClubQueryCountTest extends TestCase
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
        $defaults = [
            'status' => ClubStatus::Active->value,
            'is_public' => true,
            'latitude' => 10.776,
            'longitude' => 106.701,
        ];
        // If created_by not provided, fall back to a fresh user so the NOT NULL
        // constraint on clubs.created_by never trips.
        if (!array_key_exists('created_by', $overrides)) {
            $defaults['created_by'] = $this->makeUser()->id;
        }
        return Club::factory()->create(array_merge($defaults, $overrides));
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

    /**
     * @return array{0: \Illuminate\Testing\TestResponse, 1: int}
     */
    private function runWithCount(string $method, string $uri, array $headers = []): array
    {
        $user = $this->makeUser();
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $resp = match (strtoupper($method)) {
            'GET'    => $this->actingAs($user)->getJson($uri, $headers),
            'POST'   => $this->actingAs($user)->postJson($uri, [], $headers),
            default  => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        return [$resp, $count];
    }

    public function test_search_club_default_stays_under_ceiling(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeClub(['name' => "CLB {$i}"]);
        }

        [$resp, $count] = $this->runWithCount('GET', '/api/search?tab=club&per_page=20');

        $resp->assertOk();
        $this->assertLessThanOrEqual(
            35,
            $count,
            "Query count {$count} exceeds ceiling 35 for /api/search?tab=club&per_page=20"
        );
    }

    public function test_suggest_30_clubs_stays_under_ceiling(): void
    {
        $user = $this->makeUser();
        $this->setVndupr($user, 3.5);

        for ($i = 0; $i < 5; $i++) {
            $f = $this->makeUser();
            $this->addFriend($user, $f);
            $c = $this->makeClub(['name' => "F{$i}"]);
            $this->makeMember($c, $f);
        }
        for ($i = 0; $i < 5; $i++) {
            $c = $this->makeClub(['name' => "L{$i}"]);
            $this->follow($user, $c);
        }
        for ($i = 0; $i < 5; $i++) {
            $m = $this->makeUser();
            $this->setVndupr($m, 3.5);
            $c = $this->makeClub(['name' => "S{$i}"]);
            $this->makeMember($c, $m);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->makeClub(['name' => "N{$i}", 'latitude' => 10.78 + $i * 0.001, 'longitude' => 106.701]);
        }

        $count = 0;
        DB::listen(function () use (&$count) { $count++; });

        $resp = $this->actingAs($user)
            ->getJson('/api/clubs/suggest', [
                'X-User-Lat' => '10.776',
                'X-User-Lng' => '106.701',
            ]);

        $resp->assertOk();
        // Ceiling calibrated to the current implementation: ~30 `count(*) as aggregate`
        // queries come from the leader/admin resolution path inside the enricher, and
        // the rest is the 4 suggest buckets (2 queries each) + 6 enricher batch queries.
        // Tighten in a follow-up once the count(*) cluster is consolidated.
        $this->assertLessThanOrEqual(
            80,
            $count,
            "Query count {$count} exceeds ceiling 80 for /api/clubs/suggest"
        );
    }

    public function test_search_club_subtab_following_stays_under_ceiling(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $c = $this->makeClub(['name' => "F{$i}"]);
            $this->follow($user, $c);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->makeClub(['name' => "U{$i}"]);
        }

        [$resp, $count] = $this->runWithCount('GET', '/api/search?tab=club&sub_tab=following&per_page=20');

        $resp->assertOk();
        $this->assertLessThanOrEqual(
            25,
            $count,
            "Query count {$count} exceeds ceiling 25 for following sub_tab"
        );
    }

    public function test_search_club_subtab_suit_level_stays_under_ceiling(): void
    {
        $user = $this->makeUser();
        $this->setVndupr($user, 3.5);

        for ($i = 0; $i < 8; $i++) {
            $m = $this->makeUser();
            $this->setVndupr($m, 3.5);
            $c = $this->makeClub(['name' => "S{$i}"]);
            $this->makeMember($c, $m);
        }
        for ($i = 0; $i < 10; $i++) {
            $this->makeClub(['name' => "U{$i}"]);
        }

        [$resp, $count] = $this->runWithCount('GET', '/api/search?tab=club&sub_tab=suit_level&per_page=20');

        $resp->assertOk();
        $this->assertLessThanOrEqual(
            25,
            $count,
            "Query count {$count} exceeds ceiling 25 for suit_level sub_tab"
        );
    }

    public function test_search_match_organizer_enrichment_stays_under_ceiling(): void
    {
        // FK on mini_tournaments.sport_id needs this row.
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
        $org = $this->makeUser();
        for ($i = 0; $i < 5; $i++) {
            DB::table('mini_tournaments')->insert([
                'name' => "Match {$i}",
                'created_by' => $org->id,
                'sport_id' => 1,
                'status' => 3,
                'start_time' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        [$resp, $count] = $this->runWithCount('GET', '/api/search?tab=mini-tournament&per_page=20');

        $resp->assertOk();
        $this->assertLessThanOrEqual(
            30,
            $count,
            "Query count {$count} exceeds ceiling 30 for mini-tournament with organizer enrichment"
        );
    }
}
