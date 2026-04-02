<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'role',
        'is_invite_by_organizer',
        'checked_in_at',
        'is_absent',
    ];
    const ROLE_ORGANIZER = 1;
    const ROLE_STAFF = 2;

    const ROLE_REFEREE = 3;
    const ROLES = [
        self::ROLE_ORGANIZER,
        self::ROLE_STAFF,
        self::ROLE_REFEREE,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    protected $casts = [
        'is_absent' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    public function getRoleTextAttribute()
    {
        return match ($this->role) {
            self::ROLE_ORGANIZER => 'Organizer',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_REFEREE => 'Referee',
            default => 'Unknown',
        };
    }
}
