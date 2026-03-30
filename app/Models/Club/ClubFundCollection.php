<?php

namespace App\Models\Club;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClubFundCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'club_activity_id',
        'title',
        'description',
        'target_amount',
        'amount_per_member',
        'collected_amount',
        'currency',
        'start_date',
        'end_date',
        'status',
        'qr_code_url',
        'created_by',
        'included_in_club_fund',
    ];

    protected $casts = [
        'status' => ClubFundCollectionStatus::class,
        'included_in_club_fund' => 'boolean',
        'target_amount' => 'decimal:2',
        'amount_per_member' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function activity()
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contributions()
    {
        return $this->hasMany(ClubFundContribution::class);
    }

    public function confirmedContributions()
    {
        return $this->hasMany(ClubFundContribution::class)->where('status', ClubFundContributionStatus::Confirmed);
    }

    public function assignedMembers()
    {
        return $this->belongsToMany(User::class, 'club_fund_collection_members')
            ->withPivot('amount_due')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', ClubFundCollectionStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === ClubFundCollectionStatus::Active;
    }

    public function scopePending($query)
    {
        return $query->where('status', ClubFundCollectionStatus::Pending);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ClubFundCollectionStatus::Completed);
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->target_amount == 0) {
            return 0;
        }
        return min(100, ($this->collected_amount / $this->target_amount) * 100);
    }

    public function isCompleted()
    {
        return $this->status === ClubFundCollectionStatus::Completed || $this->collected_amount >= $this->target_amount;
    }

    public function updateCollectedAmount()
    {
        $this->collected_amount = $this->confirmedContributions()->sum('amount');
        $this->save();
    }

    /**
     * Tự động chuyển status sang completed nếu quá end_date
     */
    public function checkAndUpdateStatus()
    {
        if ($this->status === ClubFundCollectionStatus::Active
            && $this->end_date
            && now()->isAfter($this->end_date->endOfDay())) {
            $this->update(['status' => ClubFundCollectionStatus::Completed]);
            return true;
        }
        return false;
    }

    /**
     * Scope để lọc các đợt thu đang active (chưa quá end_date)
     */
    public function scopeActiveAndNotExpired($query)
    {
        return $query->where('status', ClubFundCollectionStatus::Active)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now()->startOfDay());
            });
    }
}
