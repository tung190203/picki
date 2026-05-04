<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentFundContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_fund_collection_id',
        'user_id',
        'amount',
        'receipt_url',
        'note',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    public function collection()
    {
        return $this->belongsTo(TournamentFundCollection::class, 'tournament_fund_collection_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function confirm(): void
    {
        $this->update(['status' => self::STATUS_CONFIRMED]);
        $this->collection->updateCollectedAmount();
    }

    public function reject(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }
}
