<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $last = $this->lastMessage;

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'name' => $this->name,
            'last_message' => $last?->content,
            'last_message_at' => $last?->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
