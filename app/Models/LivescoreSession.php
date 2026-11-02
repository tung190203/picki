<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivescoreSession extends Model
{
    use HasFactory;

    public const THEME_DARK = 'dark';
    public const THEME_LIGHT = 'light';
    public const THEME_PHOTO = 'photo';

    protected $fillable = [
        'match_id',
        'tournament_id',
        'theme',
        'bg_image_url',
        'overlay_level',
        'sponsor_logo_url',
        'public_token',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'match_id' => 'integer',
            'tournament_id' => 'integer',
            'overlay_level' => 'integer',
            'ended_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    /**
     * Token 32 hex ký tự cho link chiếu công khai (ai có link đều xem được).
     */
    public static function generatePublicToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}