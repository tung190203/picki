<?php

namespace App\Models\Club;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'club_chat_messages';
    protected $fillable = ['conversation_id', 'user_id', 'content', 'type'];

    public function conversation()
    {
        return $this->belongsTo(ClubChatConversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
