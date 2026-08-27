<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiniTournamentPlayerPair extends Model
{
    use HasFactory;

    protected $table = 'mini_tournament_player_pairs';

    protected $fillable = [
        'mini_tournament_id',
        'player1_id',
        'player2_id',
        'pair_color',
    ];

    /**
     * Get the mini tournament that owns this pair.
     */
    public function miniTournament(): BelongsTo
    {
        return $this->belongsTo(MiniTournament::class);
    }

    /**
     * Check if a user is part of this pair.
     * Always uses user_id - backend resolves guest flag via mini_participants table when needed.
     */
    public function hasPlayer(int $userId): bool
    {
        return (int) $this->player1_id === $userId || (int) $this->player2_id === $userId;
    }

    /**
     * Get the partner of a player in this pair.
     * Returns partner's user_id.
     */
    public function getPartnerId(int $userId): ?int
    {
        if ((int) $this->player1_id === $userId) {
            return (int) $this->player2_id;
        }
        if ((int) $this->player2_id === $userId) {
            return (int) $this->player1_id;
        }
        return null;
    }
}
