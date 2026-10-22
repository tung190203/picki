<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blocked_user_id',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }

    public static function isBlocked(int $userId1, int $userId2): bool
    {
        return static::where(function ($q) use ($userId1, $userId2) {
            $q->where('user_id', $userId1)->where('blocked_user_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('user_id', $userId2)->where('blocked_user_id', $userId1);
        })->exists();
    }

    public static function isBlockedBy(int $userId, int $blockedUserId): bool
    {
        return static::where('user_id', $userId)
            ->where('blocked_user_id', $blockedUserId)
            ->exists();
    }
}
