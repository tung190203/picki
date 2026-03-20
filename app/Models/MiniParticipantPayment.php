<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiniParticipantPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mini_tournament_id',
        'participant_id',
        'user_id',
        'amount',
        'status',
        'receipt_image',
        'note',
        'admin_note',
        'paid_at',
        'confirmed_at',
        'confirmed_by',
        'guest_ids',
    ];

    protected $casts = [
        'guest_ids' => 'array',
    ];

    const PER_PAGE = 20;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    const STATUS = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
        self::STATUS_REJECTED,
    ];

    /**
     * Get the tournament this payment belongs to
     */
    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class);
    }

    /**
     * Get the participant this payment belongs to
     */
    public function participant()
    {
        return $this->belongsTo(MiniParticipant::class);
    }

    /**
     * Get the user who made the payment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who confirmed/rejected the payment
     */
    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Chờ thanh toán',
            self::STATUS_PAID => 'Đã thanh toán',
            self::STATUS_CONFIRMED => 'Đã xác nhận',
            self::STATUS_REJECTED => 'Bị từ chối',
            default => 'Không xác định',
        };
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is paid (awaiting confirmation)
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if payment is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if payment is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Scope to get payments by tournament
     */
    public function scopeByTournament($query, int $tournamentId)
    {
        return $query->where('mini_tournament_id', $tournamentId);
    }

    /**
     * Scope to get payments by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get paid (awaiting confirmation) payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }
}
