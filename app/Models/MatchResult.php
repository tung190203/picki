<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    protected $table = 'match_results';

    protected $fillable = [
        'match_id',
        'team_id',
        'score',
        'set_number',
        'won_match',
        'confirmed',
        'team_score',
        'opponent_score',
        'serving_position',
    ];

    protected $casts = [
        'score' => 'integer',
        'set_number' => 'integer',
        'team_score' => 'integer',
        'opponent_score' => 'integer',
        'serving_position' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
