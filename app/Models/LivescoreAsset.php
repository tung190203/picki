<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivescoreAsset extends Model
{
    use HasFactory;

    public const KIND_BG = 'bg';
    public const KIND_SPONSOR_LOGO = 'sponsor_logo';

    protected $fillable = [
        'tournament_id',
        'kind',
        'url',
        'width',
        'height',
        'size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'tournament_id' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }
}