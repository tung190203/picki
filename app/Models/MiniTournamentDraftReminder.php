<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiniTournamentDraftReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'mini_tournament_id',
        'user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
