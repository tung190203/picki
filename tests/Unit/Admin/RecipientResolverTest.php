<?php

namespace Tests\Unit\Admin;

use App\Enums\AdminPushNotification\ActivityLevel;
use App\Enums\AdminPushNotification\RecipientType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\Resolvers\ActivityLevelResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\AllUsersResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\ClubMembersResolver;
use App\Services\Admin\AdminPushNotification\Resolvers\SpecificUsersResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ], $attrs));

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token-' . $user->id,
            'platform' => 'android',
            'is_enabled' => true,
            'last_seen_at' => now(),
        ]);

        return $user;
    }

    public function test_all_users_resolver_excludes_banned_guest_merged_and_disabled_devices(): void
    {
        // 100 active users
        User::factory()->count(100)->create()->each(function ($u) {
            DeviceToken::create([
                'user_id' => $u->id, 'token' => 't-' . $u->id,
                'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now(),
            ]);
        });
        // 5 banned
        User::factory()->count(5)->create(['is_banned' => true])->each(function ($u) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'b-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()]);
        });
        // 3 guest
        User::factory()->count(3)->create(['is_guest' => true])->each(function ($u) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'g-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()]);
        });
        // 2 merged
        User::factory()->count(2)->create(['is_merged' => true])->each(function ($u) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'm-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()]);
        });
        // 10 với disabled devices
        User::factory()->count(10)->create()->each(function ($u) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'd-' . $u->id, 'platform' => 'android', 'is_enabled' => false, 'last_seen_at' => now()]);
        });

        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::ALL;
        $resolver = AllUsersResolver::makeFor($campaign);

        $this->assertSame(100, $resolver->buildQuery([])->count());
    }

    public function test_club_resolver_only_includes_joined_active_members(): void
    {
        $club = Club::factory()->create();

        // 50 active members
        User::factory()->count(50)->create()->each(function ($u) use ($club) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 't-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()]);
            ClubMember::create([
                'club_id' => $club->id, 'user_id' => $u->id,
                'membership_status' => \App\Enums\ClubMembershipStatus::Joined,
                'status' => \App\Enums\ClubMemberStatus::Active,
            ]);
        });
        // 20 pending
        User::factory()->count(20)->create()->each(function ($u) use ($club) {
            DeviceToken::create(['user_id' => $u->id, 'token' => 'p-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()]);
            ClubMember::create([
                'club_id' => $club->id, 'user_id' => $u->id,
                'membership_status' => \App\Enums\ClubMembershipStatus::Pending,
                'status' => \App\Enums\ClubMemberStatus::Pending,
            ]);
        });

        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::CLUB;
        $resolver = ClubMembersResolver::makeFor($campaign);

        $this->assertSame(50, $resolver->buildQuery(['club_id' => $club->id])->count());
    }

    public function test_activity_level_resolver_hot(): void
    {
        // 30 active trong 7d
        User::factory()->count(30)->create(['last_active_at' => now()->subDays(3)])->each(fn($u) =>
            DeviceToken::create(['user_id' => $u->id, 'token' => 'h-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()])
        );
        // 20 active 7-30d
        User::factory()->count(20)->create(['last_active_at' => now()->subDays(15)])->each(fn($u) =>
            DeviceToken::create(['user_id' => $u->id, 'token' => 'w-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()])
        );
        // 50 older
        User::factory()->count(50)->create(['last_active_at' => now()->subDays(60)])->each(fn($u) =>
            DeviceToken::create(['user_id' => $u->id, 'token' => 'c-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now()])
        );

        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::ACTIVITY;
        $resolver = ActivityLevelResolver::makeFor($campaign);

        $this->assertSame(30, $resolver->buildQuery(['level' => 'HOT'])->count());
    }

    public function test_specific_users_resolver_filters_by_ids(): void
    {
        $users = User::factory()->count(5)->create();
        User::factory()->count(3)->create(); // not in list

        $users->each(fn($u) => DeviceToken::create([
            'user_id' => $u->id, 'token' => 't-' . $u->id, 'platform' => 'android', 'is_enabled' => true, 'last_seen_at' => now(),
        ]));

        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::USERS;
        $resolver = SpecificUsersResolver::makeFor($campaign);

        $ids = $users->pluck('id')->toArray();
        $this->assertSame(5, $resolver->buildQuery(['user_ids' => $ids])->count());
    }

    public function test_specific_users_resolver_returns_empty_for_empty_ids(): void
    {
        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::USERS;
        $resolver = SpecificUsersResolver::makeFor($campaign);

        $this->assertSame(0, $resolver->buildQuery(['user_ids' => []])->count());
    }
}