<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiniTeamMember extends Model
{
    use HasFactory;
    protected $fillable = [
        'mini_team_id',
        'user_id',
        'is_guest',
        'guest_name',
        'guest_avatar',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
    public function miniTeam()
    {
        return $this->belongsTo(MiniTeam::class, 'mini_team_id');
    }

    /**
     * MiniParticipant tương ứng trong cùng kèo (dùng để biết member là guest/user ảo).
     * Lookup trực tiếp — không qua Eloquent relation vì cần tournament_id từ miniTeam đã eager-load.
     */
    public function miniTournamentParticipant(): ?MiniParticipant
    {
        $tournamentId = $this->miniTeam?->mini_tournament_id;
        if (!$tournamentId) {
            return null;
        }
        $query = MiniParticipant::where('mini_tournament_id', $tournamentId);
        if ($this->user_id !== null) {
            $query->where('user_id', $this->user_id);
        } else {
            $query->whereNull('user_id')->where('guest_name', $this->guest_name);
        }
        return $query->first();
    }
}