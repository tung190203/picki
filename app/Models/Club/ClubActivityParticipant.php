<?php

namespace App\Models\Club;

use App\Enums\ClubActivityParticipantStatus;
use App\Models\User;
use App\Models\Club\ClubWalletTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubActivityParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_activity_id',
        'user_id',
        'status',
        'wallet_transaction_id',
        'sport_score',
        'vndupr_score',
        'checked_in_at',
        'is_absent',
    ];

    protected $casts = [
        'status' => ClubActivityParticipantStatus::class,
        'sport_score' => 'decimal:2',
        'vndupr_score' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'is_absent' => 'boolean',
    ];

    public function activity()
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function walletTransaction()
    {
        return $this->belongsTo(ClubWalletTransaction::class, 'wallet_transaction_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', ClubActivityParticipantStatus::Pending);
    }

    public function scopeInvited($query)
    {
        return $query->where('status', ClubActivityParticipantStatus::Invited);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', ClubActivityParticipantStatus::Accepted);
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', ClubActivityParticipantStatus::Declined);
    }

    public function scopeAttended($query)
    {
        return $query->where('status', ClubActivityParticipantStatus::Attended);
    }

    public function accept()
    {
        $this->update(['status' => ClubActivityParticipantStatus::Accepted]);
    }

    public function decline()
    {
        $this->update(['status' => ClubActivityParticipantStatus::Declined]);
    }

    public function markAsAttended()
    {
        $this->update(['status' => ClubActivityParticipantStatus::Attended]);
    }

    public function markAsAbsent()
    {
        $this->update(['status' => ClubActivityParticipantStatus::Absent]);
    }
}
