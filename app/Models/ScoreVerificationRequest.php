<?php

namespace App\Models;

use App\Enums\ScoreVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreVerificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'user_id',
        'score_type',
        'submitted_score',
        'image_path',
        'status',
        'reviewer_id',
        'reviewed_at',
        'rejection_reason',
        'award_anchor_badge',
    ];

    protected $casts = [
        'submitted_score' => 'decimal:3',
        'award_anchor_badge' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', ScoreVerificationStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ScoreVerificationStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', ScoreVerificationStatus::REJECTED);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            $date = now()->format('Ymd');
            $lastRequest = static::whereDate('created_at', today())
                ->orderBy('id', 'desc')
                ->first();
            $sequence = $lastRequest ? ((int) substr($lastRequest->request_number, -6)) + 1 : 1;
            $request->request_number = 'SV-' . $date . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
