<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    use HasFactory;

    const TYPE_DM = 'dm';
    const TYPE_TOURNAMENT = 'tournament';
    const TYPE_MINI_TOURNAMENT = 'mini_tournament';
    const TYPE_CLUB = 'club';

    protected $fillable = [
        'type',
        'target_id',
        'title',
        'avatar_url',
        'last_message_id',
        'last_message_at',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'is_stranger', 'has_replied', 'last_read_message_id', 'last_read_at', 'unread_count', 'is_muted'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function getParticipantForUser(int $userId): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $userId);
    }

    public function isDm(): bool
    {
        return $this->type === self::TYPE_DM;
    }
}
