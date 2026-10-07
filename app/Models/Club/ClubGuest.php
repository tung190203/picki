<?php

namespace App\Models\Club;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubGuest extends Model
{
    protected $table = 'club_guests';

    protected $fillable = [
        'club_id',
        'user_id',
        'play_count',
        'first_played_at',
        'last_played_at',
        'is_invited',
    ];

    protected $casts = [
        'play_count' => 'integer',
        'first_played_at' => 'datetime',
        'last_played_at' => 'datetime',
        'is_invited' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function scopeNotInvited($query)
    {
        return $query->where('is_invited', false);
    }
}
