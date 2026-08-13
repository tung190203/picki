<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMerge extends Model
{
    protected $fillable = [
        'survivor_user_id',
        'merged_user_id',
        'performed_by',
        'duplicate_count',
        'duplicate_override',
        'matches_before_survivor',
        'matches_before_merged',
        'duplicate_matches_removed',
        'matches_after_merge',
        'estimated_rating',
        'final_rating',
        'selected_info_source',
        'confirmation_name',
        'status',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'duplicate_override' => 'boolean',
        'duplicate_count' => 'integer',
        'matches_before_survivor' => 'integer',
        'matches_before_merged' => 'integer',
        'duplicate_matches_removed' => 'integer',
        'matches_after_merge' => 'integer',
        'estimated_rating' => 'decimal:3',
        'final_rating' => 'decimal:3',
        'selected_info_source' => 'array',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function survivor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'survivor_user_id');
    }

    public function mergedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_user_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
