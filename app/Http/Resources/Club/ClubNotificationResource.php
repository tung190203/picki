<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class ClubNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = auth()->id();
        $isReadByMe = null;

        if ($userId && $this->created_by === $userId) {
            $isReadByMe = true;
        } elseif ($userId) {
            // Lấy từ relation nếu có eager-load, fallback về giá trị fromExists
            $myRecipient = $this->relationLoaded('recipients')
                ? $this->recipients->firstWhere('user_id', $userId)
                : null;

            if ($myRecipient) {
                $isReadByMe = (bool) $myRecipient->is_read;
            } elseif ($this->getAttribute('recipient_for_user') !== null) {
                $isReadByMe = (bool) $this->getAttribute('recipient_for_user');
            } elseif (isset($this->is_read_by_me)) {
                $isReadByMe = (bool) $this->is_read_by_me;
            }
        }

        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'club_notification_type_id' => $this->club_notification_type_id,
            'title' => $this->title,
            'content' => $this->content,
            'attachment_url' => $this->attachment_url,
            'priority' => $this->priority,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'is_pinned' => $this->is_pinned,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'created_by' => $this->created_by,
            'recipients_count' => (int) ($this->recipients_count ?? 0),
            'read_count' => (int) ($this->read_count ?? 0),
            'is_read_by_me' => $isReadByMe,
            'type' => $this->whenLoaded('type'),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
