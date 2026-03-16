<?php

namespace App\Models;

use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MiniParticipant extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'mini_tournament_id',
        'type',
        'user_id',
        'team_id',
        'is_confirmed',
        'is_invited',
        'payment_status',
    ];

    protected $casts = [
        'is_invited' => 'boolean',
        'payment_status' => PaymentStatusEnum::class,
    ];

    const PER_PAGE = 20;

    public function miniTournament()
    {
        return $this->belongsTo(MiniTournament::class);
    }

    // Nếu là user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithFullRelations($query) {
        return $query->with('user.sports.scores', 'user.sports.sport');
    }

    public function scopeLoadFullRelations()
    {
        return $this->load('user.sports.scores', 'user.sports.sport');
    }

    /**
     * Get payments for this participant
     */
    public function payments()
    {
        return $this->hasMany(MiniParticipantPayment::class);
    }

    /**
     * Get pending payment for this participant
     */
    public function pendingPayment()
    {
        return $this->hasOne(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_PENDING);
    }

    /**
     * Get paid payment for this participant
     */
    public function paidPayment()
    {
        return $this->hasOne(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_PAID);
    }

    /**
     * Get confirmed payment for this participant
     */
    public function confirmedPayment()
    {
        return $this->hasOne(MiniParticipantPayment::class)->where('status', MiniParticipantPayment::STATUS_CONFIRMED);
    }

    /**
     * Check if participant has pending payment status
     */
    public function isPendingPayment(): bool
    {
        return $this->payment_status === PaymentStatusEnum::PENDING;
    }

    /**
     * Check if participant has confirmed payment status
     */
    public function isConfirmedPayment(): bool
    {
        return $this->payment_status === PaymentStatusEnum::CONFIRMED;
    }

    /**
     * Check if participant has cancelled payment status
     */
    public function isCancelledPayment(): bool
    {
        return $this->payment_status === PaymentStatusEnum::CANCELLED;
    }

    /**
     * Set payment status to confirmed
     */
    public function confirmPayment(): void
    {
        $this->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
    }

    /**
     * Set payment status to cancelled
     */
    public function cancelPayment(): void
    {
        $this->update(['payment_status' => PaymentStatusEnum::CANCELLED]);
    }

    /**
     * Scope: Filter participants with confirmed payment
     */
    public function scopeWithConfirmedPayment($query)
    {
        return $query->where('payment_status', PaymentStatusEnum::CONFIRMED);
    }

    /**
     * Scope: Filter participants with pending payment
     */
    public function scopeWithPendingPayment($query)
    {
        return $query->where('payment_status', PaymentStatusEnum::PENDING);
    }

    /**
     * Scope: Filter participants with cancelled payment
     */
    public function scopeWithCancelledPayment($query)
    {
        return $query->where('payment_status', PaymentStatusEnum::CANCELLED);
    }
}
