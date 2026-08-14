<?php

namespace Tests\Unit\Admin;

use App\Enums\AdminPushNotification\CampaignStatus;
use App\Enums\AdminPushNotification\RecipientType;
use App\Jobs\SendAdminPushNotificationCampaignJob;
use App\Models\AdminPushNotificationCampaign;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendAdminPushNotificationCampaignJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_is_idempotent_when_called_twice(): void
    {
        $user = User::factory()->create();
        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token-1',
            'platform' => 'android',
            'is_enabled' => true,
            'last_seen_at' => now(),
        ]);

        $campaign = AdminPushNotificationCampaign::create([
            'title' => 'Test',
            'content' => 'Body',
            'action_type' => 'NONE',
            'recipient_type' => RecipientType::ALL,
            'recipient_config' => ['type' => 'ALL'],
            'send_type' => 'IMMEDIATE',
            'status' => CampaignStatus::Scheduled->value,
            'created_by' => $user->id,
        ]);

        // Mock FirebaseService::sendMulticast để không gọi thật
        $mock = $this->mock(FirebaseService::class, function ($mock) {
            $mock->shouldReceive('sendMulticast')->andReturn([
                'success' => 1,
                'failed' => 0,
                'invalid_tokens' => [],
            ]);
        });

        $job = new SendAdminPushNotificationCampaignJob($campaign->id);

        // Lần 1: process
        $job->handle($mock);
        $campaign->refresh();
        $firstStatus = $campaign->status;
        $firstSentAt = $campaign->sent_at;

        // Lần 2: idempotent - không process lại
        $job2 = new SendAdminPushNotificationCampaignJob($campaign->id);
        $job2->handle($mock);
        $campaign->refresh();

        // Status không thay đổi (vẫn SENT)
        $this->assertEquals($firstStatus, $campaign->status);
        $this->assertEquals($firstSentAt, $campaign->sent_at);
    }
}