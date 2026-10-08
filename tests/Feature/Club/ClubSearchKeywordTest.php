<?php

namespace Tests\Feature\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClubSearchKeywordTest extends TestCase
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

    public function test_keyword_matches_club_name(): void
    {
        $user = $this->makeUser();
        $matchClub = $this->makeClub(['name' => 'Hà Nội Pickleball Club']);
        $otherClub = $this->makeClub(['name' => 'Sài Gòn Tennis']);

        $resp = $this->actingAs($user)
            ->getJson('/api/search?tab=club&keyword=' . urlencode('Hà Nội Pickleball') . '&per_page=20');

        $resp->assertOk();
        $data = $resp->json('data.data');
        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($matchClub->id), 'Expected matching club in results');
        $this->assertFalse($ids->contains($otherClub->id), 'Unrelated club should not match name');
    }

    public function test_keyword_matches_admin_full_name(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $admin->update(['full_name' => 'Nguyễn Văn Admin']);

        $clubA = $this->makeClub(['name' => 'CLB A', 'created_by' => $user->id]);
        $this->makeMember($clubA, $admin, ClubMemberRole::Admin->value);

        $clubB = $this->makeClub(['name' => 'CLB B', 'created_by' => $user->id]);
        $unrelated = $this->makeUser();
        $unrelated->update(['full_name' => 'Trần Văn Khác']);
        $this->makeMember($clubB, $unrelated, ClubMemberRole::Admin->value);

        $resp = $this->actingAs($user)
            ->getJson('/api/search?tab=club&keyword=' . urlencode('Nguyễn Văn Admin') . '&per_page=20');

        $resp->assertOk();
        $ids = collect($resp->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($clubA->id), 'Club with matching admin should be returned');
        $this->assertFalse($ids->contains($clubB->id), 'Club with non-matching admin should not be returned');
    }

    public function test_keyword_with_no_match_returns_empty_list(): void
    {
        $user = $this->makeUser();
        $this->makeClub(['name' => 'CLB Real']);

        $resp = $this->actingAs($user)
            ->getJson('/api/search?tab=club&keyword=zzz_no_match_xyz&per_page=20');

        $resp->assertOk();
        $this->assertSame(0, $resp->json('data.meta.total'));
        $this->assertSame([], $resp->json('data.data'));
    }

    public function test_no_keyword_returns_all_clubs(): void
    {
        $user = $this->makeUser();
        $a = $this->makeClub(['name' => 'CLB Alpha']);
        $b = $this->makeClub(['name' => 'CLB Beta']);
        $c = $this->makeClub(['name' => 'CLB Gamma']);

        $resp = $this->actingAs($user)
            ->getJson('/api/search?tab=club&per_page=20');

        $resp->assertOk();
        $ids = collect($resp->json('data.data'))->pluck('id')->all();
        $this->assertGreaterThanOrEqual(3, count($ids));
        $this->assertTrue(in_array($a->id, $ids, true));
        $this->assertTrue(in_array($b->id, $ids, true));
        $this->assertTrue(in_array($c->id, $ids, true));
    }

    public function test_keyword_with_like_wildcards_does_not_throw(): void
    {
        $user = $this->makeUser();
        $this->makeClub(['name' => 'CLB Normal']);

        // `%` and `_` are LIKE wildcards. Either BE escapes them (literal match → empty)
        // or treats them as wildcards (match everything). Both are acceptable; the contract
        // we pin is: response is 200, JSON is well-formed, no exception.
        $resp = $this->actingAs($user)
            ->getJson('/api/search?tab=club&keyword=' . urlencode('100%_match%') . '&per_page=20');

        $resp->assertOk();
        $this->assertIsArray($resp->json('data.data'));
        $this->assertArrayHasKey('meta', $resp->json('data'));
    }
}
