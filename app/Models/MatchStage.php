<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchStage extends Model
{
    protected $table = 'match_stages';

    protected $fillable = [
        'match_id',
        'stage_no',
        'cumulative_score_a',
        'cumulative_score_b',
        'stage_score_a',
        'stage_score_b',
        'status',
        'winner_team_id',
        'target_point',
        'completed_at',
    ];

    protected $casts = [
        'stage_no' => 'integer',
        'cumulative_score_a' => 'integer',
        'cumulative_score_b' => 'integer',
        'stage_score_a' => 'integer',
        'stage_score_b' => 'integer',
        'target_point' => 'integer',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MatchStageMember::class, 'match_stage_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function setCompleted(int $winnerTeamId): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'winner_team_id' => $winnerTeamId,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get members belonging to a specific team in this stage.
     *
     * @param int $teamId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMembersForTeam(int $teamId)
    {
        return $this->members()->where('team_id', $teamId)->with('user')->get();
    }
}
