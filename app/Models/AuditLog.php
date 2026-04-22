<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'note',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByTarget($query, string $targetType, ?int $targetId = null)
    {
        $query->where('target_type', $targetType);
        if ($targetId !== null) {
            $query->where('target_id', $targetId);
        }
        return $query;
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
