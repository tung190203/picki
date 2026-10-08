<?php

namespace Tests\Feature\Club;

use App\Enums\ClubStatus;
use App\Models\Club\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Non-regression: ensure the Search/Club work didn't break the
 * existing tabs (Match, Tournament, User) or the Follow/Unfollow
 * flow and the Club detail endpoint.
 */
class ClubSearchRegressionTest extends TestCase
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
        ], $overrides));
    }

    private function seedSport(): void
    {
        // FK on mini_tournaments.sport_id and tournaments.sport_id needs this row.
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
    }

    public function test_search_match_returns_data_and_meta(): void
    {
        $this->seedSport();
        $user = $this->makeUser();
        $org = $this->makeUser();
        DB::table('mini_tournaments')->insert([
            'name' => 'Test Match',
            'created_by' => $org->id,
            'sport_id' => 1,
            'status' => 3,
            'start_time' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user)->getJson('/api/search?tab=mini-tournament&per_page=20');
        $resp->assertOk();
        $this->assertIsArray($resp->json('data.data'));
        $this->assertArrayHasKey('total', $resp->json('data.meta'));
    }

    public function test_search_tournament_returns_data_and_meta(): void
    {
        $this->seedSport();
        $user = $this->makeUser();
        $org = $this->makeUser();
        DB::table('tournaments')->insert([
            'name' => 'Test Tournament',
            'created_by' => $org->id,
            'sport_id' => 1,
            'status' => 0,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(7),
            'duration' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($user)->getJson('/api/search?tab=tournament&per_page=20');
        $resp->assertOk();
        $this->assertIsArray($resp->json('data.data'));
        $this->assertArrayHasKey('total', $resp->json('data.meta'));
    }

    public function test_search_user_by_keyword_still_works(): void
    {
        $viewer = $this->makeUser();
        $target = $this->makeUser();
        $target->update(['full_name' => 'Nguyễn Văn A']);

        $resp = $this->actingAs($viewer)
            ->getJson('/api/search?tab=user&keyword=' . urlencode('Nguyễn Văn A') . '&per_page=20');
        $resp->assertOk();
        $ids = collect($resp->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($target->id));
    }

    public function test_follow_and_unfollow_club_toggle(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClub();

        $followResp = $this->actingAs($user)
            ->postJson('/api/follows/store', [
                'followable_type' => 'club',
                'followable_id' => $club->id,
            ]);
        $followResp->assertOk();
        $this->assertDatabaseHas('follows', [
            'user_id' => $user->id,
            'followable_id' => $club->id,
            'followable_type' => Club::class,
        ]);

        $unfollowResp = $this->actingAs($user)
            ->postJson('/api/follows/delete', [
                'followable_type' => 'club',
                'followable_id' => $club->id,
            ]);
        $unfollowResp->assertOk();
        $this->assertDatabaseMissing('follows', [
            'user_id' => $user->id,
            'followable_id' => $club->id,
            'followable_type' => Club::class,
        ]);
    }

    public function test_club_detail_endpoint_returns_200(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClub([
            'recruitment_status' => 'open',
        ]);

        $resp = $this->actingAs($user)->getJson("/api/clubs/{$club->id}");
        $resp->assertOk();

        $body = $resp->json();
        $this->assertIsArray($body);
        $payload = $body['data'] ?? $body;
        $this->assertSame($club->id, $payload['id']);
    }
}
