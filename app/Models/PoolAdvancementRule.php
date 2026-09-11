<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoolAdvancementRule extends Model
{
    protected $fillable = [
        'tournament_type_id',
        'group_id',
        'is_virtual',
        'virtual_index',
        'rank',
        'next_match_id',
        'next_position',
    ];

    protected $casts = [
        'is_virtual' => 'boolean',
        'virtual_index' => 'integer',
    ];

    /**
     * Scope: các rule ảo (cross-group slot) chưa được resolve.
     */
    public function scopeVirtual($q)
    {
        return $q->where('is_virtual', true);
    }

    /**
     * Scope: các rule đang trỏ vào group thật.
     */
    public function scopeReal($q)
    {
        return $q->where(function ($q) {
            $q->whereNull('is_virtual')->orWhere('is_virtual', false);
        });
    }

    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function nextMatch()
    {
        return $this->belongsTo(Matches::class, 'next_match_id');
    }
}