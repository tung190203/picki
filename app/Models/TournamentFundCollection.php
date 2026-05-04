<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentFundCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'club_id',
        'title',
        'description',
        'target_amount',
        'collected_amount',
        'currency',
        'start_date',
        'end_date',
        'status',
        'qr_code_url',
        'created_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function club()
    {
        return $this->belongsTo(\App\Models\Club\Club::class);
    }

    public function contributions()
    {
        return $this->hasMany(\App\Models\TournamentFundContribution::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'tournament_fund_collection_members')
            ->withPivot('amount_due')
            ->withTimestamps();
    }

    public function confirmedContributions()
    {
        return $this->hasMany(\App\Models\TournamentFundContribution::class)
            ->where('status', 'confirmed');
    }

    public function pendingContributions()
    {
        return $this->hasMany(\App\Models\TournamentFundContribution::class)
            ->where('status', 'pending');
    }

    public function updateCollectedAmount(): void
    {
        $this->collected_amount = $this->confirmedContributions()->sum('amount');
        $this->save();
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount == 0) {
            return 0;
        }
        return min(100, ($this->collected_amount / $this->target_amount) * 100);
    }
}
