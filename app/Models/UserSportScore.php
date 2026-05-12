<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSportScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_sport_id',
        'score_type',
        'score_value'
    ];

    protected $casts = [
        'score_value' => 'float',
    ];

    const PERSONAL_SCORE = 'personal_score';
    const VNDUPR_SCORE = 'vndupr_score';
    const DUPR_SCORE = 'dupr_score';

    public function userSport()
    {
        return $this->belongsTo(UserSport::class);
    }
}
