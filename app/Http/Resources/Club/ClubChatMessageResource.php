<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class ClubChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'content' => $this->content,
            'type' => $this->type,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'sender' => $user ? [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'short_name' => $user->short_name ?? null,
                'avatar_url' => $user->avatar_url,
            ] : null,
        ];
    }
}
