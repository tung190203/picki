<?php

namespace App\Http\Resources\Chat;

use App\Models\Chat\Conversation;
use App\Services\Chat\UserRelationshipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = auth()->id();
        $currentParticipant = $this->participants->firstWhere('user_id', $currentUserId);

        $title = $this->title;
        $avatarUrl = $this->avatar_url;
        $contextTag = null;
        $otherUser = null;

        if ($this->type === Conversation::TYPE_DM) {
            $otherParticipant = $this->participants->firstWhere('user_id', '!=', $currentUserId);
            if ($otherParticipant && $otherParticipant->user) {
                $otherUser = [
                    'id' => $otherParticipant->user->id,
                    'full_name' => $otherParticipant->user->full_name,
                    'avatar_url' => $otherParticipant->user->avatar_url,
                    'rating' => $otherParticipant->user->self_score ?? null,
                ];
                $title = $otherParticipant->user->full_name;
                $avatarUrl = $otherParticipant->user->avatar_url;

                // Lấy Context Tag (VD: Cùng CLB..., Đã đấu chung kèo...)
                $relService = app(UserRelationshipService::class);
                $context = $relService->resolveContextTag($currentUserId, $otherParticipant->user->id);
                $contextTag = $context['context_tag'];
            }
        } elseif ($this->type === Conversation::TYPE_MINI_TOURNAMENT) {
            $contextTag = 'Kèo đấu';
            if ((!$title || !$avatarUrl) && $this->target_id) {
                $mini = \App\Models\MiniTournament::find($this->target_id);
                $title = $title ?: ($mini?->name ?? 'Kèo đấu');
                $avatarUrl = $avatarUrl ?: $mini?->poster;
            }
        } elseif ($this->type === Conversation::TYPE_TOURNAMENT) {
            $contextTag = 'Giải đấu';
            if ((!$title || !$avatarUrl) && $this->target_id) {
                $tour = \App\Models\Tournament::find($this->target_id);
                $title = $title ?: ($tour?->name ?? 'Giải đấu');
                $avatarUrl = $avatarUrl ?: $tour?->poster;
            }
        } elseif ($this->type === Conversation::TYPE_CLUB) {
            $contextTag = 'CLB';
            if ((!$title || !$avatarUrl) && $this->target_id) {
                $club = \App\Models\Club\Club::find($this->target_id);
                $title = $title ?: ($club ? 'CLB ' . $club->name : 'CLB');
                $avatarUrl = $avatarUrl ?: $club?->avatar_url;
            }
        }

        if ($avatarUrl && !str_starts_with($avatarUrl, 'http') && !str_starts_with($avatarUrl, '/storage/')) {
            $avatarUrl = '/storage/' . ltrim($avatarUrl, '/');
        }

        $isBlockedByMe = false;
        $isBlockedByOther = false;

        if ($this->type === Conversation::TYPE_DM && $otherUser) {
            $otherUserId = (int) $otherUser['id'];
            $isBlockedByMe = \App\Models\Chat\UserBlock::isBlockedBy((int) $currentUserId, $otherUserId);
            $isBlockedByOther = \App\Models\Chat\UserBlock::isBlockedBy($otherUserId, (int) $currentUserId);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'target_id' => $this->target_id,
            'title' => $title,
            'avatar_url' => $avatarUrl,
            'context_tag' => $contextTag,
            'is_stranger' => (bool) ($currentParticipant?->is_stranger ?? false),
            'unread_count' => (int) ($currentParticipant?->unread_count ?? 0),
            'is_muted' => (bool) ($currentParticipant?->is_muted ?? false),
            'is_archived' => (bool) $this->is_archived,
            'archive_tag' => $this->is_archived ? '[Lưu trữ]' : null,
            'is_blocked_by_me' => $isBlockedByMe,
            'is_blocked_by_other' => $isBlockedByOther,
            'is_blocked' => $isBlockedByMe || $isBlockedByOther,
            'last_message' => $this->lastMessage ? new ChatMessageResource($this->lastMessage) : null,
            'last_message_at' => optional($this->last_message_at)->toDateTimeString(),
            'other_user' => $otherUser,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
