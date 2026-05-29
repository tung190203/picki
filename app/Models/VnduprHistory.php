<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VnduprHistory extends Model
{
    use HasFactory;

    protected $table = 'vndupr_history';
    protected $fillable = [
        'user_id',
        'match_id',
        'mini_match_id',
        'quick_match_id',
        'score_before',
        'score_after',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
    public function miniMatch()
    {
        return $this->belongsTo(MiniMatch::class, 'mini_match_id');
    }
    public function quickMatch()
    {
        return $this->belongsTo(QuickMatch::class, 'quick_match_id');
    }
}
