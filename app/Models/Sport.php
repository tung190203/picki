<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sport extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'slug',
    ];

    const PER_PAGE = 20;

    public const PICKLEBALL_SLUG = 'pickleball';
    public const PICKLEBALL_ID = 1;

    public function tournaments()
    {
        return $this->hasMany(MiniTournament::class, 'sport_id');
    }

    public function competitionLocations()
    {
        return $this->belongsToMany(CompetitionLocation::class, 'competition_location_sport', 'sport_id', 'competition_location_id');
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($sport) {
            if (empty($sport->slug)) {
                $sport->slug = Str::slug($sport->name);
            }
        });
        static::updating(function ($sport) {
            if (empty($sport->slug)) {
                $sport->slug = Str::slug($sport->name);
            }
        });
    }
}
