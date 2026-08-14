<?php

namespace App\Models;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\CampaignStatus;
use App\Enums\AdminPushNotification\RecipientType;
use App\Enums\AdminPushNotification\SendType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPushNotificationCampaign extends Model
{
    use HasFactory;

    protected $table = 'admin_push_notification_campaigns';

    protected $fillable = [
        'created_by',
        'title',
        'content',
        'image_url',
        'action_type',
        'action_id',
        'recipient_type',
        'recipient_config',
        'estimated_recipient_count',
        'actual_recipient_count',
        'success_count',
        'failure_count',
        'send_type',
        'scheduled_at',
        'sent_at',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'action_type' => ActionType::class,
        'recipient_type' => RecipientType::class,
        'send_type' => SendType::class,
        'status' => CampaignStatus::class,
        'recipient_config' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'estimated_recipient_count' => 'integer',
        'actual_recipient_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'action_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', CampaignStatus::Scheduled)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeForHistory($query)
    {
        return $query->whereIn('status', [
            CampaignStatus::Sent,
            CampaignStatus::Partial,
            CampaignStatus::Failed,
            CampaignStatus::Cancelled,
            CampaignStatus::Processing,
        ]);
    }

    public function isScheduledReady(): bool
    {
        return $this->status === CampaignStatus::Scheduled
            && $this->scheduled_at !== null
            && $this->scheduled_at->lte(now());
    }
}