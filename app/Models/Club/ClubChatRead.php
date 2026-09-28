<?php

namespace App\Models\Club;

use Illuminate\Database\Eloquent\Model;

class ClubChatRead extends Model
{
    protected $table = 'club_chat_reads';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_message_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(ClubChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
