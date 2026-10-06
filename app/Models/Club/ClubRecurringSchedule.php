<?php

namespace App\Models\Club;

use App\Models\CompetitionLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClubRecurringSchedule extends Model
{
    protected $table = 'club_recurring_schedules';

    protected $fillable = [
        'club_id',
        'day_of_week',
        'start_time',
        'end_time',
        'note',
        'position',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'position' => 'integer',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Sân nhà mà lịch này áp dụng.
     * Nếu relation rỗng → lịch áp dụng cho toàn bộ sân nhà của CLB.
     */
    public function homeCourts(): BelongsToMany
    {
        return $this->belongsToMany(
            CompetitionLocation::class,
            'club_recurring_schedule_locations',
            'club_recurring_schedule_id',
            'competition_location_id'
        );
    }
}
