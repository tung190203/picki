<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyRank extends Model
{
    use HasFactory;

    protected $table = 'weekly_ranks';

    protected $fillable = [
        'user_id',
        'sport_id',
        'rank',
        'recorded_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sport()
    {
        return $this->belongsTo(Sport::class, 'sport_id');
    }

    public function scopeCurrentSnapshot($query, int $sportId)
    {
        return $query->where('sport_id', $sportId)->whereNull('recorded_at');
    }

    public function scopeLatestSunday($query, int $sportId)
    {
        return $query->where('sport_id', $sportId)
            ->whereNotNull('recorded_at')
            ->orderByDesc('recorded_at');
    }
}
