<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = auth()->id();
        $isOwn = (int) $this->user_id === (int) $currentUserId;

        $status = 'sent';
        if ($isOwn) {
            $conversation = $this->relationLoaded('conversation')
                ? $this->conversation
                : $this->conversation()->with('participants')->first();

            if ($conversation) {
                $otherParticipants = $conversation->relationLoaded('participants')
                    ? $conversation->participants->where('user_id', '!=', $currentUserId)
                    : $conversation->participants()->where('user_id', '!=', $currentUserId)->get();

                $isSeen = $otherParticipants->contains(function ($p) {
                    return (int) ($p->last_read_message_id ?? 0) >= (int) $this->id;
                });

                if ($isSeen) {
                    $status = 'seen';
                }
            }
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'avatar_url' => $this->user->avatar_url,
                'rating' => $this->user->vndupr_rating ?? $this->user->rating ?? null,
            ] : null,
            'type' => $this->type,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'card_data' => $this->isCard()
                ? app(\App\Services\Chat\ChatCardResolverService::class)->resolveCard($this->type, $this->metadata)
                : null,
            'is_own' => $isOwn,
            'status' => $status,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
