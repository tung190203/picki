<?php

namespace App\Services\Admin\AdminPushNotification;

use App\Enums\AdminPushNotification\CampaignStatus;
use App\Enums\AdminPushNotification\RecipientType;
use App\Enums\AdminPushNotification\SendType;
use App\Jobs\SendAdminPushNotificationCampaignJob;
use App\Models\AdminPushNotificationCampaign;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Admin\AdminPushNotification\PushNotificationRecipientResolver;
use App\Services\Admin\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPushNotificationService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Estimate số lượng recipient đủ điều kiện cho một cấu hình.
     */
    public function estimateRecipients(string $recipientType, array $config): int
    {
        $campaign = $this->buildDraftCampaignForEstimate($recipientType, $config);
        return $this->countEligibleRecipients($campaign);
    }

    /**
     * Preview data để hiển thị confirm modal.
     */
    public function preview(string $recipientType, array $config, string $sendType, ?string $scheduledAt): array
    {
        $campaign = $this->buildDraftCampaignForEstimate($recipientType, $config);
        $resolverData = CampaignRecipientResolverFactory::makeWithConfig($campaign);
        /** @var PushNotificationRecipientResolver $resolver */
        $resolver = $resolverData['resolver'];

        return [
            'recipient_label' => $resolver->label($resolverData['config']),
            'estimated_recipient_count' => $this->countEligibleRecipients($campaign),
            'warnings' => $resolver->warnings($resolverData['config']),
            'send_type' => $sendType,
            'scheduled_at' => $scheduledAt,
        ];
    }

    /**
     * Tạo campaign + upload ảnh (nếu có) + dispatch job (nếu IMMEDIATE).
     */
    public function createCampaign(array $data, ?UploadedFile $image, User $admin): AdminPushNotificationCampaign
    {
        $imageUrl = null;
        if ($image) {
            $imageUrl = $this->uploadImage($image);
        }

        $sendType = SendType::from($data['send_type']);
        $initialStatus = $sendType === SendType::IMMEDIATE
            ? CampaignStatus::PROCESSING
            : CampaignStatus::SCHEDULED;

        $campaign = DB::transaction(function () use ($data, $imageUrl, $admin, $sendType, $initialStatus) {
            $campaign = AdminPushNotificationCampaign::create([
                'created_by' => $admin->id,
                'title' => $data['title'],
                'content' => $data['content'],
                'image_url' => $imageUrl,
                'action_type' => $data['action_type'],
                'action_id' => $data['action_id'] ?? null,
                'recipient_type' => $data['recipient_type'],
                'recipient_config' => $data['recipient_config'],
                'estimated_recipient_count' => 0,
                'send_type' => $sendType->value,
                'scheduled_at' => isset($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null,
                'status' => $initialStatus->value,
            ]);

            $estimated = $this->countEligibleRecipients($campaign);
            $campaign->update(['estimated_recipient_count' => $estimated]);

            $this->auditLogService->log(
                $admin,
                'create_admin_push_notification',
                AdminPushNotificationCampaign::class,
                $campaign->id,
                null,
                [
                    'title' => $campaign->title,
                    'recipient_type' => $campaign->recipient_type->value,
                    'send_type' => $campaign->send_type->value,
                    'estimated_recipient_count' => $estimated,
                    'scheduled_at' => $campaign->scheduled_at?->toIsoString(),
                ],
                "Created push notification campaign: {$campaign->title}"
            );

            return $campaign;
        });

        if ($sendType === SendType::IMMEDIATE) {
            SendAdminPushNotificationCampaignJob::dispatch($campaign->id);
        }

        return $campaign;
    }

    /**
     * Gửi test notification cho admin hiện tại (sync, không qua queue).
     */
    public function sendTest(User $admin, string $title, string $content, ?UploadedFile $image = null, ?string $imageUrl = null, ?string $actionType = null, ?int $actionId = null): array
    {
        if ($image) {
            $imageUrl = $this->uploadImage($image);
        }

        $devices = DeviceToken::where('user_id', $admin->id)
            ->where('is_enabled', true)
            ->get();

        if ($devices->isEmpty()) {
            throw new \App\Exceptions\BusinessException(
                'Tài khoản admin chưa đăng ký thiết bị để nhận thông báo thử.',
                422
            );
        }

        $data = [
            'type' => 'ADMIN_PUSH_TEST',
            'admin_id' => (string) $admin->id,
        ];

        if ($actionType && $actionType !== 'NONE' && $actionId) {
            $data['action_type'] = $actionType;
            $data['action_id'] = (string) $actionId;
            $data['action_url'] = match ($actionType) {
                'TOURNAMENT' => "tournament-detail/{$actionId}",
                'MINI_TOURNAMENT' => "mini-tournament-detail/{$actionId}",
                'CLUB' => "club-detail/{$actionId}",
                default => null,
            };
        }

        $firebase = app(\App\Services\FirebaseService::class);
        $tokens = $devices->pluck('token')->toArray();
        $result = $firebase->sendMulticast($tokens, $title, $content, $data, $imageUrl);

        $this->auditLogService->log(
            $admin,
            'send_admin_push_test',
            User::class,
            $admin->id,
            null,
            [
                'title' => $title,
                'content' => $content,
                'devices_count' => count($tokens),
                'success' => $result['success'],
                'failed' => $result['failed'],
            ],
            "Sent test push notification"
        );

        return [
            'devices_count' => count($tokens),
            'success' => $result['success'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Upload image lên Storage::disk('public'), trả về public URL.
     */
    public function uploadImage(UploadedFile $image): string
    {
        $filename = 'admin-push-notifications/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
        Storage::disk('public')->put($filename, file_get_contents($image));
        return asset('storage/' . $filename);
    }

    /**
     * Đếm số user đủ điều kiện cho một campaign.
     */
    public function countEligibleRecipients(AdminPushNotificationCampaign $campaign): int
    {
        $resolverData = CampaignRecipientResolverFactory::makeWithConfig($campaign);
        /** @var PushNotificationRecipientResolver $resolver */
        $resolver = $resolverData['resolver'];

        return $resolver->buildQuery($resolverData['config'])->count();
    }

    /**
     * Tạo campaign instance tạm (chưa save) để dùng cho estimate/preview.
     */
    protected function buildDraftCampaignForEstimate(string $recipientType, array $config): AdminPushNotificationCampaign
    {
        $campaign = new AdminPushNotificationCampaign();
        $campaign->recipient_type = RecipientType::from($recipientType);
        $campaign->recipient_config = $config;
        return $campaign;
    }
}