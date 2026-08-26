<?php

namespace App\Models;

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
        'player1_is_guest',
        'player2_is_guest',
        'pair_color',
    ];

    protected $casts = [
        'player1_is_guest' => 'boolean',
        'player2_is_guest' => 'boolean',
    ];

    /**
     * Get the mini tournament that owns this pair.
     */
    public function miniTournament(): BelongsTo
    {
        return $this->belongsTo(MiniTournament::class);
    }

    /**
     * Check if a player is part of this pair.
     */
    public function hasPlayer(int $playerId, bool $isGuest = false): bool
    {
        if ($isGuest) {
            return (string) $this->player1_id === (string) $playerId && $this->player1_is_guest
                || (string) $this->player2_id === (string) $playerId && $this->player2_is_guest;
        }
        return (string) $this->player1_id === (string) $playerId && !$this->player1_is_guest
            || (string) $this->player2_id === (string) $playerId && !$this->player2_is_guest;
    }

    /**
     * Get the partner of a player in this pair.
     */
    public function getPartnerId(int $playerId, bool $isGuest = false): ?array
    {
        if ((string) $this->player1_id === (string) $playerId && $this->player1_is_guest === $isGuest) {
            return [
                'id' => $this->player2_id,
                'is_guest' => $this->player2_is_guest,
            ];
        }
        if ((string) $this->player2_id === (string) $playerId && $this->player2_is_guest === $isGuest) {
            return [
                'id' => $this->player1_id,
                'is_guest' => $this->player1_is_guest,
            ];
        }
        return null;
    }
}
