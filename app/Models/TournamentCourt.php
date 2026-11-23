<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TournamentCourt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tournament_id',
        'name',
        'sort_order',
        'referee_user_id',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'sort_order' => 'integer',
        'referee_user_id' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_user_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Matches::class, 'court_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourtAssignment::class, 'court_id');
    }
}
