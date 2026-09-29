<?php

namespace App\Events;

use App\Models\Club\ClubChatMessage;
use App\Models\Club\ClubChatRead;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubChatMessageRead implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $clubId,
        public int $conversationId,
        public ClubChatRead $read
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('club.' . $this->clubId)];
    }

    public function broadcastAs(): string
    {
        return 'club.chat.message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->read->user_id,
            'last_read_message_id' => $this->read->last_read_message_id,
            'read_at' => $this->read->read_at?->toIso8601String(),
        ];
    }
}
