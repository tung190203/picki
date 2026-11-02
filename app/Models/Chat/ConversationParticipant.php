<?php

namespace App\Models\Chat;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'is_stranger',
        'has_replied',
        'last_read_message_id',
        'last_read_at',
        'unread_count',
        'is_muted',
    ];

    protected $casts = [
        'is_stranger' => 'boolean',
        'has_replied' => 'boolean',
        'is_muted' => 'boolean',
        'last_read_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
