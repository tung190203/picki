<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    use HasFactory;

    protected $table = 'user_achievements';

    protected $fillable = [
        'user_id',
        'club_id',
        'event_type',
        'event_id',
        'event_name',
        'event_date',
        'event_status',
        'is_club_hosted',
        'rank',
        'medal',
        'points',
        'is_star',
        'is_cup',
        'is_guest_snapshot',
        'partner_names',
    ];

        protected $casts = [
            'event_date' => 'datetime',
            'is_club_hosted' => 'boolean',
            'is_star' => 'boolean',
            'is_cup' => 'boolean',
            'is_guest_snapshot' => 'boolean',
            'rank' => 'integer',
            'points' => 'integer',
            'partner_names' => 'array',
        ];

    public const TYPE_MINI_TOURNAMENT = 'mini_tournament';
    public const TYPE_TOURNAMENT = 'tournament';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
