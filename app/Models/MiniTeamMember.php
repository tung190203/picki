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
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
    public function miniTeam()
    {
        return $this->belongsTo(MiniTeam::class, 'mini_team_id');
    }
}
