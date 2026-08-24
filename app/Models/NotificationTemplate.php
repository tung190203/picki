<?php

namespace App\Models;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\RecipientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'name',
        'title',
        'content',
        'action_type',
        'action_id',
        'recipient_type',
        'recipient_config',
    ];

    protected $casts = [
        'action_type' => ActionType::class,
        'recipient_type' => RecipientType::class,
        'recipient_config' => 'array',
        'action_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
