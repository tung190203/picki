<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreeMatch extends Model
{
    use HasFactory;

    public const STATUS_PLAYING = 'playing';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'creator_user_id',
        'title',
        'rules',
        'team_a',
        'team_b',
        'score',
        'status',
    ];

    protected $casts = [
        'creator_user_id' => 'integer',
        'rules' => 'array',
        'team_a' => 'array',
        'team_b' => 'array',
        'score' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }
}
