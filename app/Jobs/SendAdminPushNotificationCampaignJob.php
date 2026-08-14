<?php

namespace App\Jobs;

use App\Enums\AdminPushNotification\CampaignStatus;
use App\Models\AdminPushNotificationCampaign;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AdminPushCampaignNotification;
use App\Services\Admin\AdminPushNotification\CampaignRecipientResolverFactory;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAdminPushNotificationCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes for large campaigns
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $campaignId) {}

    public function handle(FirebaseService $firebase): void
    {
        $campaign = AdminPushNotificationCampaign::find($this->campaignId);

        if (!$campaign) {
            Log::warning('SendAdminPushNotificationCampaignJob: campaign not found', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        // Idempotency: atomic update status PROCESSING nếu đang SCHEDULED/PROCESSING/DRAFT
        $claimed = AdminPushNotificationCampaign::where('id', $campaign->id)
            ->whereIn('status', [
                CampaignStatus::SCHEDULED->value,
                CampaignStatus::DRAFT->value,
                CampaignStatus::PROCESSING->value,
            ])
            ->update(['status' => CampaignStatus::PROCESSING->value]);

        if ($claimed === 0) {
            Log::info('SendAdminPushNotificationCampaignJob: already processed', [
                'campaign_id' => $this->campaignId,
                'status' => $campaign->status->value,
            ]);
            return;
        }

        $campaign->refresh();
        Log::info('Starting push notification campaign', [
            'campaign_id' => $campaign->id,
            'recipient_type' => $campaign->recipient_type->value,
            'recipient_config' => $campaign->recipient_config,
            'estimated_count' => $campaign->estimated_recipient_count,
        ]);

        // Validate recipient_config trước khi xử lý
        $config = $campaign->recipient_config ?? [];
        $this->validateRecipientConfig($campaign->recipient_type, $config);

        $resolverData = CampaignRecipientResolverFactory::makeWithConfig($campaign);
        $query = $resolverData['resolver']->buildQuery($resolverData['config']);

        $data = [
            'type' => 'ADMIN_PUSH_NOTIFICATION',
            'campaign_id' => (string) $campaign->id,
        ];

        if ($campaign->action_type && $campaign->action_type !== \App\Enums\AdminPushNotification\ActionType::NONE && $campaign->action_id) {
            $data['action_type'] = $campaign->action_type->value;
            $data['action_id'] = (string) $campaign->action_id;
            $data['action_url'] = match ($campaign->action_type->value) {
                'TOURNAMENT' => "tournament-detail/{$campaign->action_id}",
                'MINI_TOURNAMENT' => "mini-tournament-detail/{$campaign->action_id}",
                'CLUB' => "club-detail/{$campaign->action_id}",
                default => null,
            };
        }

        // Lấy danh sách user IDs đủ điều kiện
        $userIds = $query->pluck('users.id')->toArray();

        $totalSuccess = 0;
        $totalFailed = 0;
        $actualRecipientCount = 0;

        // Track users đã notify (mỗi user chỉ notify 1 lần)
        $notifiedUserIds = [];

        if (empty($userIds)) {
            Log::info('No users found for campaign', ['campaign_id' => $campaign->id]);
        } else {
            // Dùng cursor để iterate qua devices (tiết kiệm memory)
            $devices = DeviceToken::whereIn('user_id', $userIds)
                ->where('is_enabled', true)
                ->cursor();

            foreach ($devices as $device) {
                $actualRecipientCount++;

                try {
                    $sent = $firebase->sendToDevice(
                        $device,
                        $campaign->title,
                        $campaign->content,
                        $data,
                        $campaign->image_url
                    );

                    if ($sent) {
                        $totalSuccess++;
                        // Track user để notify (chỉ notify 1 lần cho mỗi user)
                        $notifiedUserIds[$device->user_id] = true;
                    } else {
                        $totalFailed++;
                    }
                } catch (\Throwable $e) {
                    $totalFailed++;
                    Log::error('Failed to send admin push to device', [
                        'device_id' => $device->id,
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Notify users để lưu vào bảng notifications (chỉ users có ít nhất 1 device gửi thành công)
        $usersToNotify = User::whereIn('id', array_keys($notifiedUserIds))->get();
        foreach ($usersToNotify as $user) {
            $user->notify(new AdminPushCampaignNotification($campaign));
        }

        Log::info('Users notified for campaign', [
            'campaign_id' => $campaign->id,
            'notified_count' => $usersToNotify->count(),
        ]);

        // Xác định final status
        $finalStatus = match (true) {
            $actualRecipientCount === 0 => CampaignStatus::FAILED,
            $totalFailed === 0 => CampaignStatus::SENT,
            $totalSuccess === 0 => CampaignStatus::FAILED,
            default => CampaignStatus::PARTIAL,
        };

        $campaign->update([
            'status' => $finalStatus->value,
            'sent_at' => now(),
            'actual_recipient_count' => $actualRecipientCount,
            'success_count' => $totalSuccess,
            'failure_count' => $totalFailed,
            'error_message' => $actualRecipientCount === 0
                ? 'Không tìm thấy thiết bị enabled nào cho các user đủ điều kiện.'
                : null,
            'metadata' => array_merge($campaign->metadata ?? [], [
                'completed_at' => now()->toIsoString(),
            ]),
        ]);

        Log::info('Push notification campaign completed', [
            'campaign_id' => $campaign->id,
            'status' => $finalStatus->value,
            'actual_recipient_count' => $actualRecipientCount,
            'success' => $totalSuccess,
            'failed' => $totalFailed,
        ]);
    }

    protected function validateRecipientConfig(\App\Enums\AdminPushNotification\RecipientType $recipientType, array $config): void
    {
        $requiredFields = match ($recipientType) {
            \App\Enums\AdminPushNotification\RecipientType::CLUB => ['club_id'],
            \App\Enums\AdminPushNotification\RecipientType::USERS => ['user_ids'],
            \App\Enums\AdminPushNotification\RecipientType::ACTIVITY => ['level'],
            default => [],
        };

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || (is_array($config[$field]) && empty($config[$field]))) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                "Campaign config missing required fields for recipient_type {$recipientType->value}: " . implode(', ', $missingFields)
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendAdminPushNotificationCampaignJob failed', [
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);

        $campaign = AdminPushNotificationCampaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update([
                'status' => CampaignStatus::FAILED->value,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}