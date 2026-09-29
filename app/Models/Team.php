<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tournament_id',
        'tournament_type_id',
        'avatar',
    ];

    protected $appends = ['is_my_team'];

    const PER_PAGE = 15;

    public function tournament()
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')->withTrashed();
    }

    /**
     * Guest participants (is_guest=true) đã gắn vào team qua team_members.participant_id.
     * Dùng cho view only — guest không thuộc $team->members vì không có User.
     */
    public function guestMembers()
    {
        return $this->hasMany(\App\Models\TeamMember::class, 'team_id')
            ->whereNotNull('participant_id')
            ->with('participant.user', 'participant.guarantor');
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn (string $value = null) => $value
                ? (
                    str_starts_with($value, 'http')
                        ? $value
                        : config('app.frontend_url') . '/storage/' . $value
                  )
                : null,
        );
    }

    protected function isMyTeam(): Attribute
    {
        return Attribute::make(
            get: function () {
                $userId = Auth::id();
                if (!$userId) {
                    return false;
                }
                return $this->members->contains('id', $userId);
            },
        );
    }
}
