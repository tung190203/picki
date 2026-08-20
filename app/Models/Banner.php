<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'internal_name',
        'image_url',
        'link',
        'link_type',
        'link_value',
        'start_date',
        'end_date',
        'audience_segment_ids',
        'display_order',
        'is_enabled',
        'type',
        'is_active',
        'order',
        'created_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'order' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'audience_segment_ids' => 'array',
    ];

    protected $appends = [
        'status_badge',
        'days_remaining',
    ];

    const PER_PAGE = 10;

    /**
     * Relationship to creator admin
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tính toán status_badge động cho Admin:
     * - 'disabled'  : Tắt thủ công (is_enabled = false và chưa hết hạn)
     * - 'expired'   : Hết hạn (today > end_date)
     * - 'scheduled' : Lên lịch (is_enabled = true và today < start_date)
     * - 'expiring'  : Sắp hết hạn (Đang chạy, remaining <= 5 days)
     * - 'live'      : Đang chạy (is_enabled = true, today trong khoảng)
     */
    public function getStatusBadgeAttribute(): string
    {
        $today = Carbon::today();

        if ($this->end_date && Carbon::parse($this->end_date)->lt($today)) {
            return 'expired';
        }

        if (!$this->is_enabled) {
            return 'disabled';
        }

        if ($this->start_date && Carbon::parse($this->start_date)->gt($today)) {
            return 'scheduled';
        }

        if ($this->end_date) {
            $daysLeft = $today->diffInDays(Carbon::parse($this->end_date), false);
            if ($daysLeft >= 0 && $daysLeft <= 5) {
                return 'expiring';
            }
        }

        return 'live';
    }

    /**
     * Lấy số ngày còn lại đến end_date
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->end_date);

        if ($endDate->lt($today)) {
            return 0;
        }

        return (int) $today->diffInDays($endDate, false);
    }
}
