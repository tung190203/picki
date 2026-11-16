<?php

namespace App\Services\Chat;

use App\Helpers\StringHelper;
use App\Models\Chat\ChatRecentSearch;
use App\Models\Chat\Conversation;
use App\Models\Chat\UserBlock;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatRecentSearchService
{
    public function __construct(
        protected UserRelationshipService $relationshipService
    ) {}

    /**
     * Lấy danh sách user đã tìm kiếm gần đây để nhắn tin (sắp xếp mới nhất trước).
     */
    public function getRecentSearches(User $authUser, int $limit = ChatRecentSearch::MAX_RECENT_ITEMS): Collection
    {
        $limit = max(1, min(50, $limit));
        $blockedIds = $this->getBlockedUserIds((int) $authUser->id);

        $records = ChatRecentSearch::query()
            ->where('user_id', $authUser->id)
            ->whereHas('targetUser', function ($q) use ($blockedIds) {
                $q->where('is_banned', false)
                    ->where('is_guest', false)
                    ->where('visibility', '!=', 'private');

                if (!empty($blockedIds)) {
                    $q->whereNotIn('id', $blockedIds);
                }
            })
            ->with(['targetUser.sports.scores'])
            ->orderByDesc('searched_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($records->isEmpty()) {
            return collect();
        }

        $targetIds = $records->pluck('target_user_id')->all();
        $dmMap = $this->getExistingDmConversationMap((int) $authUser->id, $targetIds);

        return $records->map(function (ChatRecentSearch $record) use ($authUser, $dmMap) {
            return $this->formatUserItem(
                $record->targetUser,
                (int) $authUser->id,
                $dmMap[$record->target_user_id] ?? null,
                $record->searched_at?->toDateTimeString()
            );
        })->values();
    }

    /**
     * Lưu (hoặc cập nhật thời gian) một user vào danh sách tìm kiếm gần đây.
     */
    public function recordSearch(User $authUser, int $targetUserId): array
    {
        $authUserId = (int) $authUser->id;

        if ($authUserId === $targetUserId) {
            throw new Exception('Không thể lưu chính mình vào tìm kiếm gần đây');
        }

        $targetUser = User::with('sports.scores')->find($targetUserId);
        if (!$targetUser || $targetUser->is_guest || $targetUser->is_banned) {
            throw new Exception('Người dùng không tồn tại hoặc không khả dụng để nhắn tin');
        }

        if (UserBlock::isBlocked($authUserId, $targetUserId)) {
            throw new Exception('Không thể lưu người dùng đang bị chặn');
        }

        $now = now();

        DB::transaction(function () use ($authUserId, $targetUserId, $now) {
            ChatRecentSearch::updateOrCreate(
                [
                    'user_id' => $authUserId,
                    'target_user_id' => $targetUserId,
                ],
                [
                    'searched_at' => $now,
                ]
            );

            $this->pruneOldSearches($authUserId);
        });

        $dmMap = $this->getExistingDmConversationMap($authUserId, [$targetUserId]);

        return $this->formatUserItem(
            $targetUser,
            $authUserId,
            $dmMap[$targetUserId] ?? null,
            $now->toDateTimeString()
        );
    }

    /**
     * Xóa một user khỏi danh sách tìm kiếm gần đây.
     */
    public function removeSearch(int $userId, int $targetUserId): bool
    {
        return ChatRecentSearch::where('user_id', $userId)
            ->where('target_user_id', $targetUserId)
            ->delete() > 0;
    }

    /**
     * Xóa toàn bộ lịch sử tìm kiếm gần đây của user.
     */
    public function clearAll(int $userId): int
    {
        return ChatRecentSearch::where('user_id', $userId)->delete();
    }

    /**
     * Tìm kiếm người dùng để bắt đầu nhắn tin.
     * Nếu từ khóa rỗng, trả về danh sách tìm kiếm gần đây.
     */
    public function searchUsersForChat(User $authUser, string $keyword = '', int $limit = 20): Collection
    {
        $keyword = trim($keyword);
        $limit = max(1, min(50, $limit));

        if ($keyword === '') {
            return $this->getRecentSearches($authUser, $limit);
        }

        $authUserId = (int) $authUser->id;
        $blockedIds = $this->getBlockedUserIds($authUserId);

        $query = User::query()
            ->with(['sports.scores'])
            ->where('id', '!=', $authUserId)
            ->where('is_banned', false)
            ->where('is_guest', false)
            ->where('visibility', '!=', 'private');

        if (!empty($blockedIds)) {
            $query->whereNotIn('id', $blockedIds);
        }

        StringHelper::applyWhereLikeIgnoreAccents($query, 'full_name', $keyword);

        $query->where(function ($sub) use ($authUserId) {
            $sub->where('visibility', 'open')
                ->orWhere(function ($f) use ($authUserId) {
                    $f->where('visibility', 'friend-only')
                        ->where(function ($rel) use ($authUserId) {
                            $rel->whereHas('clubs', function ($cq) use ($authUserId) {
                                $cq->whereIn('clubs.id', function ($subClub) use ($authUserId) {
                                    $subClub->select('club_id')
                                        ->from('club_members')
                                        ->where('user_id', $authUserId)
                                        ->where('membership_status', 'joined')
                                        ->where('status', 'active');
                                });
                            })
                            ->orWhereHas('participants', function ($tp) use ($authUserId) {
                                $tp->whereIn('tournament_id', function ($tq) use ($authUserId) {
                                    $tq->select('tournament_id')
                                        ->from('participants')
                                        ->where('user_id', $authUserId);
                                });
                            })
                            ->orWhereHas('miniParticipants', function ($mp) use ($authUserId) {
                                $mp->whereIn('mini_tournament_id', function ($mq) use ($authUserId) {
                                    $mq->select('mini_tournament_id')
                                        ->from('mini_participants')
                                        ->where('user_id', $authUserId);
                                });
                            });
                        });
                });
        });

        $users = $query->limit($limit)->get();
        if ($users->isEmpty()) {
            return collect();
        }

        $dmMap = $this->getExistingDmConversationMap($authUserId, $users->pluck('id')->all());

        return $users->map(function (User $u) use ($authUserId, $dmMap) {
            return $this->formatUserItem($u, $authUserId, $dmMap[$u->id] ?? null);
        })->values();
    }

    /**
     * Giữ tối đa MAX_RECENT_ITEMS mục gần nhất cho mỗi user.
     */
    protected function pruneOldSearches(int $userId): void
    {
        $keepIds = ChatRecentSearch::where('user_id', $userId)
            ->orderByDesc('searched_at')
            ->orderByDesc('id')
            ->limit(ChatRecentSearch::MAX_RECENT_ITEMS)
            ->pluck('id');

        if ($keepIds->count() >= ChatRecentSearch::MAX_RECENT_ITEMS) {
            ChatRecentSearch::where('user_id', $userId)
                ->whereNotIn('id', $keepIds)
                ->delete();
        }
    }

    /**
     * Lấy danh sách ID các user bị chặn hoặc đã chặn authUser.
     */
    protected function getBlockedUserIds(int $userId): array
    {
        $blockedByMe = UserBlock::where('user_id', $userId)->pluck('blocked_user_id');
        $blockedMe = UserBlock::where('blocked_user_id', $userId)->pluck('user_id');

        return $blockedByMe->merge($blockedMe)->unique()->values()->all();
    }

    /**
     * Map target_user_id => conversation_id cho các cuộc trò chuyện DM đã tồn tại.
     */
    protected function getExistingDmConversationMap(int $authUserId, array $targetUserIds): array
    {
        if (empty($targetUserIds)) {
            return [];
        }

        $rows = DB::table('conversations as c')
            ->join('conversation_participants as p1', function ($join) use ($authUserId) {
                $join->on('p1.conversation_id', '=', 'c.id')
                    ->where('p1.user_id', '=', $authUserId);
            })
            ->join('conversation_participants as p2', function ($join) use ($targetUserIds) {
                $join->on('p2.conversation_id', '=', 'c.id')
                    ->whereIn('p2.user_id', $targetUserIds);
            })
            ->where('c.type', Conversation::TYPE_DM)
            ->select('p2.user_id as target_user_id', 'c.id as conversation_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->target_user_id] = (int) $row->conversation_id;
        }

        return $map;
    }

    protected function formatUserItem(
        User $user,
        int $authUserId,
        ?int $conversationId = null,
        ?string $searchedAt = null
    ): array {
        $scores = $user->relationLoaded('sports')
            ? ($user->sports->firstWhere('sport_id', 1)?->scores ?? collect())
            : collect();
        $vnduprScore = $scores->firstWhere('score_type', 'vndupr_score');
        $rating = $vnduprScore?->score_value !== null
            ? round((float) $vnduprScore->score_value, 2)
            : ($user->self_score !== null ? (float) $user->self_score : null);

        $avatarUrl = $user->avatar_url;
        if ($avatarUrl && !str_starts_with($avatarUrl, 'http') && !str_starts_with($avatarUrl, '/storage/')) {
            $avatarUrl = '/storage/' . ltrim($avatarUrl, '/');
        }

        $context = $this->relationshipService->resolveContextTag($authUserId, (int) $user->id);

        $item = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $avatarUrl,
            'rating' => $rating,
            'is_acquaintance' => (bool) ($context['is_acquaintance'] ?? false),
            'context_tag' => $context['context_tag'] ?? null,
            'conversation_id' => $conversationId,
        ];

        if ($searchedAt !== null) {
            $item['searched_at'] = $searchedAt;
        }

        return $item;
    }
}

