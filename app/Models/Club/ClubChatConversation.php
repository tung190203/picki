<?php

namespace App\Models\Club;

use Illuminate\Database\Eloquent\Model;

class ClubChatConversation extends Model
{
    protected $table = 'club_chat_conversations';
    protected $fillable = ['club_id', 'name', 'last_message_id'];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function messages()
    {
        return $this->hasMany(ClubChatMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function lastMessage()
    {
        return $this->belongsTo(ClubChatMessage::class, 'last_message_id');
    }

    /** Lấy hoặc tạo conversation cho CLB. */
    public static function forClub(int $clubId, ?string $clubName = null): self
    {
        return self::firstOrCreate(
            ['club_id' => $clubId],
            ['name' => 'Nhóm chat ' . ($clubName ?? 'CLB')]
        );
    }
}
