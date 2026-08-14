<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\CampaignStatus;
use App\Enums\AdminPushNotification\RecipientType;
use App\Enums\AdminPushNotification\SendType;
use App\Models\AdminPushNotificationCampaign;
use App\Models\AuditLog;
use App\Models\Club\Club;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPushNotificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(bool $isSuper = true): User
    {
        return User::factory()->create([
            'is_super_admin' => $isSuper,
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
        ]);
    }

    private function authHeader(User $user): array
    {
        $token = $user->createToken('test')->accessToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = $this->makeAdmin(false);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/admin/push-notifications/estimate-recipients', [
                'recipient_type' => 'ALL',
                'recipient_config' => [],
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->postJson('/api/admin/push-notifications/estimate-recipients', [
            'recipient_type' => 'ALL',
            'recipient_config' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_super_admin_can_estimate(): void
    {
        $admin = $this->makeAdmin();
        // Create 3 active users with devices
        User::factory()->count(3)->create()->each(function ($u) {
            \App\Models\DeviceToken::create([
                'user_id' => $u->id, 'token' => 't-' . $u->id, 'platform' => 'android',
                'is_enabled' => true, 'last_seen_at' => now(),
            ]);
        });

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications/estimate-recipients', [
                'recipient_type' => 'ALL',
                'recipient_config' => [],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.estimated_recipient_count', 3);
    }

    public function test_history_endpoint_works(): void
    {
        $admin = $this->makeAdmin();

        AdminPushNotificationCampaign::factory()->count(2)->create([
            'created_by' => $admin->id,
            'status' => CampaignStatus::Sent,
        ]);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/admin/push-notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
    }

    public function test_show_endpoint_returns_404_for_missing_campaign(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/admin/push-notifications/999999');

        $response->assertStatus(404);
    }

    public function test_lookup_clubs_works(): void
    {
        $admin = $this->makeAdmin();
        Club::factory()->create(['name' => 'Test Club ABC']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/admin/push-notifications/lookup/clubs?keyword=Test');

        $response->assertStatus(200);
    }

    public function test_lookup_users_works(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->create(['full_name' => 'Nguyen Van Test', 'phone' => '0901234567']);

        $response = $this->withHeaders($this->authHeader($admin))
            ->getJson('/api/admin/push-notifications/lookup/users?keyword=Nguyen');

        $response->assertStatus(200);
    }

    public function test_create_validates_title_too_long(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => str_repeat('a', 51),
                'content' => 'Test content',
                'action_type' => 'NONE',
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'IMMEDIATE',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_create_validates_action_id_required_when_action_type_set(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Test',
                'content' => 'Test content',
                'action_type' => 'TOURNAMENT',
                // Missing action_id
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'IMMEDIATE',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action_id']);
    }

    public function test_create_validates_scheduled_at_future(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Test',
                'content' => 'Test content',
                'action_type' => 'NONE',
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'SCHEDULED',
                'scheduled_at' => now()->subHour()->toIsoString(),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_create_immediate_dispatches_job(): void
    {
        Queue::fake();

        $admin = $this->makeAdmin();
        User::factory()->count(2)->create()->each(function ($u) {
            \App\Models\DeviceToken::create([
                'user_id' => $u->id, 'token' => 't-' . $u->id, 'platform' => 'android',
                'is_enabled' => true, 'last_seen_at' => now(),
            ]);
        });

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Test Campaign',
                'content' => 'Hello world',
                'action_type' => 'NONE',
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'IMMEDIATE',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('admin_push_notification_campaigns', [
            'title' => 'Test Campaign',
            'status' => 'PROCESSING',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('admin_push_notification_campaigns', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'create_admin_push_notification',
        ]);
    }

    public function test_create_scheduled_does_not_dispatch_job(): void
    {
        Queue::fake();

        $admin = $this->makeAdmin();

        $response = $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Scheduled Test',
                'content' => 'Hello world',
                'action_type' => 'NONE',
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'SCHEDULED',
                'scheduled_at' => now()->addHour()->toIsoString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('admin_push_notification_campaigns', [
            'title' => 'Scheduled Test',
            'status' => 'SCHEDULED',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_create_writes_audit_log(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();

        $this->withHeaders($this->authHeader($admin))
            ->postJson('/api/admin/push-notifications', [
                'title' => 'Audit Test',
                'content' => 'Test',
                'action_type' => 'NONE',
                'recipient_type' => 'ALL',
                'recipient_config' => ['type' => 'ALL'],
                'send_type' => 'IMMEDIATE',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'create_admin_push_notification',
        ]);
    }
}