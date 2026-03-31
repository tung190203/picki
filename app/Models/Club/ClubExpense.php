<?php

namespace App\Models\Club;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'club_id',
        'club_activity_id',
        'mini_tournament_id',
        'title',
        'amount',
        'wallet_transaction_id',
        'spent_by',
        'spent_at',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_at' => 'datetime',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function activity()
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function miniTournament()
    {
        return $this->belongsTo(\App\Models\MiniTournament::class, 'mini_tournament_id');
    }

    public function spender()
    {
        return $this->belongsTo(User::class, 'spent_by');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(ClubWalletTransaction::class);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('spent_at', [$from, $to]);
    }

    public function scopeBySpender($query, $userId)
    {
        return $query->where('spent_by', $userId);
    }
}
