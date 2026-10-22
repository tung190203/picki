<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_VOICE = 'voice';
    const TYPE_FILE = 'file';
    const TYPE_CARD_MINI_TOURNAMENT = 'card_mini_tournament';
    const TYPE_CARD_MATCH = 'card_mini_tournament';
    const TYPE_CARD_TOURNAMENT = 'card_tournament';
    const TYPE_CARD_PLAYER = 'card_player';
    const TYPE_CARD_CLUB = 'card_club';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'type',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isCard(): bool
    {
        return in_array($this->type, [
            self::TYPE_CARD_MINI_TOURNAMENT,
            'card_match',
            self::TYPE_CARD_TOURNAMENT,
            self::TYPE_CARD_PLAYER,
            self::TYPE_CARD_CLUB,
        ], true);
    }
}
