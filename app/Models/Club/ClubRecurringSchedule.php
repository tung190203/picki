<?php

namespace App\Models\Club;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
