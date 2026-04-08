<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'is_confirmed',
        'is_invite_by_organizer',
        'is_guest',
        'guest_name',
        'guest_phone',
        'guest_avatar',
        'guarantor_user_id',
        'estimated_level',
        'is_pending_confirmation',
        'checked_in_at',
        'is_absent',
        'rating_before',
        'rating_after',
        'rank_before',
        'rank_after',
        'rank_change',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'is_pending_confirmation' => 'boolean',
        'estimated_level' => 'decimal:1',
        'checked_in_at' => 'datetime',
        'is_absent' => 'boolean',
        'rating_before' => 'decimal:2',
        'rating_after' => 'decimal:2',
        'rank_before' => 'integer',
        'rank_after' => 'integer',
        'rank_change' => 'integer',
    ];

    const PER_PAGE = 15;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function guarantor()
    {
        return $this->belongsTo(User::class, 'guarantor_user_id');
    }

    public static function scopeWithFullRelations($query)
    {
        return $query->with('user', 'tournament', 'user.sports.scores', 'guarantor');
    }

    public function scopeGuests($query)
    {
        return $query->where('is_guest', true);
    }

    public function scopePendingConfirmation($query)
    {
        return $query->where('is_guest', true)->where('is_pending_confirmation', true);
    }
}
