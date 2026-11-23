<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtAssignment extends Model
{
    use HasFactory;

    public const UNIT_GROUP = 'group';
    public const UNIT_SLOT = 'slot';

    protected $fillable = [
        'tournament_id',
        'unit_type',
        'group_id',
        'slot_round',
        'slot_no',
        'court_id',
        'sort_order',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'group_id' => 'integer',
        'slot_no' => 'integer',
        'court_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(TournamentCourt::class, 'court_id');
    }
}
