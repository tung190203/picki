<?php

namespace App\Events;

use App\Models\Club\ClubChatConversation;
use App\Models\Club\ClubChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ClubChatMessage $message,
        public int $clubId,
        public int $conversationId
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('club.' . $this->clubId)];
    }

    public function broadcastAs(): string
    {
        return 'club.chat.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('user');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'content' => $this->message->content,
                'type' => $this->message->type,
                'created_at' => $this->message->created_at?->format('Y-m-d H:i:s'),
                'sender' => $this->message->user ? [
                    'id' => $this->message->user->id,
                    'full_name' => $this->message->user->full_name,
                    'avatar_url' => $this->message->user->avatar_url,
                ] : null,
            ],
        ];
    }
}
