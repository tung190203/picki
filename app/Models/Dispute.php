<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    protected $fillable = [
        'match_id',
        'raised_by',
        'content',
        'status',
        'handled_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
