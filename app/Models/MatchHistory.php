<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchHistory extends Model
{
    protected $fillable = [
        'user_id',
        'quick_match_id',
        'team_side',
        'played_at',
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quickMatch(): BelongsTo
    {
        return $this->belongsTo(QuickMatch::class);
    }
}
