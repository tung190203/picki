<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchStageMember extends Model
{
    protected $table = 'match_stage_members';

    protected $fillable = [
        'match_stage_id',
        'team_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'match_stage_id' => 'integer',
        'team_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function matchStage(): BelongsTo
    {
        return $this->belongsTo(MatchStage::class, 'match_stage_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
