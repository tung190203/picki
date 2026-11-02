<?php

namespace App\Services\Chat;

use App\Helpers\StringHelper;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Http\Resources\Chat\ConversationResource;
use App\Http\Resources\NotificationResource;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InboxService
{
    /**
     * Lấy danh sách Inbox theo tab (all, messages, alerts), bộ lọc chưa đọc (is_unread) và từ khóa (q).
     */
    public function getInbox(User $user, array $params = []): array
    {
        $tab = $params['tab'] ?? 'all';
        $isUnread = filter_var($params['is_unread'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $keyword = isset($params['q']) ? trim((string) $params['q']) : null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));

        return match ($tab) {
            'messages' => $this->getMessagesTab($user, $isUnread, $page, $perPage, $keyword),
            'alerts' => $this->getAlertsTab($user, $isUnread, $page, $perPage, $keyword),
            default => $this->getAllTab($user, $isUnread, $page, $perPage, $keyword),
        };
    }

    /**
     * Tab Tin nhắn: Chỉ lấy các cuộc trò chuyện của người quen (is_stranger = false).
     * Đính kèm strangers_summary cho thẻ ghim Người lạ ở đầu danh sách.
     */
    protected function getMessagesTab(User $user, bool $isUnread, int $page, int $perPage, ?string $keyword = null): array
    {
        $query = Conversation::query()
            ->whereHas('participants', function ($p) use ($user, $isUnread) {
                $p->where('user_id', $user->id)
                    ->where('is_stranger', false);
                if ($isUnread) {
                    $p->where('unread_count', '>', 0);
                }
            });

        $this->applyNonEmptyConversationFilter($query);
        $this->applyConversationKeywordFilter($query, $user, $keyword);

        $query->with([
                'participants.user',
                'lastMessage.sender',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'tab' => 'messages',
            'strangers_summary' => $this->getStrangersSummary($user->id),
            'items' => ConversationResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Tab Thông báo: Lấy danh sách thông báo hệ thống.
     */
    protected function getAlertsTab(User $user, bool $isUnread, int $page, int $perPage, ?string $keyword = null): array
    {
        $query = $user->notifications();
        if ($isUnread) {
            $query->whereNull('read_at');
        }
        $this->applyNotificationKeywordFilter($query, $keyword);
        $query->latest();

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'tab' => 'alerts',
            'items' => NotificationResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Tab Tất cả: Trộn chung Thông báo hệ thống và Tin nhắn cá nhân người quen theo thời gian thực (latest_time DESC).
     */
    protected function getAllTab(User $user, bool $isUnread, int $page, int $perPage, ?string $keyword = null): array
    {
        // 1. Lấy conversations (is_stranger = false)
        $convQuery = Conversation::query()
            ->whereHas('participants', function ($p) use ($user, $isUnread) {
                $p->where('user_id', $user->id)
                    ->where('is_stranger', false);
                if ($isUnread) {
                    $p->where('unread_count', '>', 0);
                }
            });

        $this->applyNonEmptyConversationFilter($convQuery);
        $this->applyConversationKeywordFilter($convQuery, $user, $keyword);

        $convQuery->with(['participants.user', 'lastMessage.sender'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        $totalConvs = (clone $convQuery)->count();
        // Lấy số lượng vừa đủ cho phân trang
        $convs = $convQuery->limit($page * $perPage)->get();

        // 2. Lấy notifications
        $alertQuery = $user->notifications();
        if ($isUnread) {
            $alertQuery->whereNull('read_at');
        }
        $this->applyNotificationKeywordFilter($alertQuery, $keyword);
        $alertQuery->latest();

        $totalAlerts = (clone $alertQuery)->count();
        $alerts = $alertQuery->limit($page * $perPage)->get();

        // 3. Chuẩn hóa & Trộn theo thời gian
        $convItems = $convs->map(fn ($c) => [
            'item_type' => 'conversation',
            'id' => "conv_{$c->id}",
            'sort_time' => optional($c->last_message_at ?? $c->updated_at)->timestamp ?? 0,
            'timestamp' => optional($c->last_message_at ?? $c->updated_at)->toDateTimeString(),
            'data' => (new ConversationResource($c))->resolve(),
        ]);

        $alertItems = $alerts->map(fn ($n) => [
            'item_type' => 'alert',
            'id' => "alert_{$n->id}",
            'sort_time' => optional($n->created_at)->timestamp ?? 0,
            'timestamp' => optional($n->created_at)->toDateTimeString(),
            'data' => (new NotificationResource($n))->resolve(),
        ]);

        $merged = $convItems->concat($alertItems)->sortByDesc('sort_time')->values();
        $total = $totalConvs + $totalAlerts;
        $offset = ($page - 1) * $perPage;
        $slicedItems = $merged->slice($offset, $perPage)->values();

        return [
            'tab' => 'all',
            'strangers_summary' => $this->getStrangersSummary($user->id),
            'items' => $slicedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    /**
     * Thông tin tóm tắt hàng đợi Người lạ (Strangers queue) cho thẻ ghim đầu tab Tin nhắn.
     */
    public function getStrangersSummary(int $userId): array
    {
        $strangerConversationsQuery = Conversation::query()
            ->whereHas('participants', function ($p) use ($userId) {
                $p->where('user_id', $userId)
                    ->where('is_stranger', true);
            });
        $this->applyNonEmptyConversationFilter($strangerConversationsQuery);

        $strangerParticipants = $strangerConversationsQuery->pluck('id');

        $total = $strangerParticipants->count();
        $unreadCount = $total > 0
            ? ConversationParticipant::where('user_id', $userId)
                ->where('is_stranger', true)
                ->whereIn('conversation_id', $strangerParticipants)
                ->sum('unread_count')
            : 0;

        $latestConversation = $total > 0
            ? Conversation::whereIn('id', $strangerParticipants)
                ->with(['lastMessage.sender', 'participants.user'])
                ->orderByDesc('last_message_at')
                ->first()
            : null;

        $latestMessage = null;
        $latestSenderAvatar = null;
        $latestSenderName = null;

        if ($latestConversation) {
            if ($latestConversation->lastMessage) {
                $lastMsg = $latestConversation->lastMessage;
                $latestMessage = match ($lastMsg->type) {
                    ChatMessage::TYPE_TEXT => $lastMsg->content,
                    ChatMessage::TYPE_CARD_MINI_TOURNAMENT, 'card_match' => '[Thẻ Kèo đấu]',
                    ChatMessage::TYPE_CARD_TOURNAMENT => '[Thẻ Giải đấu]',
                    ChatMessage::TYPE_CARD_PLAYER => '[Thẻ Người chơi]',
                    ChatMessage::TYPE_CARD_CLUB => '[Thẻ CLB]',
                    ChatMessage::TYPE_IMAGE => '[Hình ảnh]',
                    ChatMessage::TYPE_VOICE => '[Tin nhắn thoại]',
                    ChatMessage::TYPE_FILE => '[Tập tin]',
                    default => '[Tin nhắn mới]',
                };
                $latestSenderAvatar = $lastMsg->sender?->avatar_url;
                $latestSenderName = $lastMsg->sender?->full_name;
            } else {
                $otherP = $latestConversation->participants->firstWhere('user_id', '!=', $userId);
                $latestSenderAvatar = $otherP?->user?->avatar_url;
                $latestSenderName = $otherP?->user?->full_name;
                $latestMessage = 'Cuộc trò chuyện mới';
            }
        }

        return [
            'total_conversations' => $total,
            'unread_count' => (int) $unreadCount,
            'latest_message' => $latestMessage,
            'latest_sender_name' => $latestSenderName,
            'latest_sender_avatar' => $latestSenderAvatar,
        ];
    }

    /**
     * Danh sách hàng đợi Tin nhắn từ người lạ.
     */
    public function getStrangersQueue(User $user, int $page = 1, int $perPage = 20, ?string $keyword = null): array
    {
        $query = Conversation::query()
            ->whereHas('participants', function ($p) use ($user) {
                $p->where('user_id', $user->id)
                    ->where('is_stranger', true);
            });

        $this->applyNonEmptyConversationFilter($query);
        $this->applyConversationKeywordFilter($query, $user, $keyword);

        $query->with([
                'participants.user',
                'lastMessage.sender',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => ConversationResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Đếm số lượng chưa đọc theo từng loại:
     * - total_unread = messages_unread + alerts_unread (strangers_unread KHÔNG cộng dồn).
     */
    public function getUnreadCounts(User $user): array
    {
        $messagesUnread = (int) ConversationParticipant::where('user_id', $user->id)
            ->where('is_stranger', false)
            ->where('is_muted', false)
            ->sum('unread_count');

        $strangersUnread = (int) ConversationParticipant::where('user_id', $user->id)
            ->where('is_stranger', true)
            ->where('is_muted', false)
            ->sum('unread_count');

        $alertsUnread = (int) $user->unreadNotifications()->count();

        $totalUnread = $messagesUnread + $alertsUnread;

        return [
            'total_unread' => $totalUnread,
            'messages_unread' => $messagesUnread,
            'alerts_unread' => $alertsUnread,
            'strangers_unread' => $strangersUnread,
        ];
    }

    /**
     * Loại bỏ các cuộc trò chuyện 1-1 (DM) mới khởi tạo khi mở màn hình chat nhưng chưa gửi bất kỳ tin nhắn nào.
     */
    protected function applyNonEmptyConversationFilter($query): void
    {
        $query->where(function ($sub) {
            $sub->where('type', '!=', Conversation::TYPE_DM)
                ->orWhereNotNull('last_message_id')
                ->orWhereHas('messages');
        });
    }

    /**
     * Bộ lọc từ khóa chung cho Conversation (tên hội thoại, tên người tham gia, nội dung tin nhắn, tên Kèo/Giải/CLB).
     * Hỗ trợ tìm kiếm tiếng Việt cả có dấu lẫn không dấu.
     */
    protected function applyConversationKeywordFilter($query, User $user, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $query->where(function ($sub) use ($keyword, $user) {
            StringHelper::applyWhereLikeIgnoreAccents($sub, 'title', $keyword);

            $sub->orWhereHas('participants', function ($p2) use ($keyword, $user) {
                $p2->where('user_id', '!=', $user->id)
                    ->whereHas('user', fn ($u) => StringHelper::applyWhereLikeIgnoreAccents($u, 'full_name', $keyword));
            })
            ->orWhereHas('messages', fn ($m) => StringHelper::applyWhereLikeIgnoreAccents($m, 'content', $keyword))
            ->orWhere(function ($mt) use ($keyword) {
                $mt->where('type', Conversation::TYPE_MINI_TOURNAMENT)
                    ->whereIn(
                        'target_id',
                        \App\Models\MiniTournament::query()
                            ->tap(fn ($q) => StringHelper::applyWhereLikeIgnoreAccents($q, 'name', $keyword))
                            ->select('id')
                    );
            })
            ->orWhere(function ($t) use ($keyword) {
                $t->where('type', Conversation::TYPE_TOURNAMENT)
                    ->whereIn(
                        'target_id',
                        \App\Models\Tournament::query()
                            ->tap(fn ($q) => StringHelper::applyWhereLikeIgnoreAccents($q, 'name', $keyword))
                            ->select('id')
                    );
            })
            ->orWhere(function ($c) use ($keyword) {
                $c->where('type', Conversation::TYPE_CLUB)
                    ->whereIn(
                        'target_id',
                        \App\Models\Club\Club::query()
                            ->tap(fn ($q) => StringHelper::applyWhereLikeIgnoreAccents($q, 'name', $keyword))
                            ->select('id')
                    );
            });
        });
    }

    /**
     * Bộ lọc từ khóa cho Notification (hỗ trợ UTF-8 trực tiếp, chuỗi Unicode json_encode \uXXXX và tìm kiếm không dấu).
     */
    public function applyNotificationKeywordFilter($query, ?string $keyword): void
    {
        StringHelper::applyNotificationDataKeywordFilter($query, $keyword);
    }

    /**
     * Đánh dấu đã đọc tất cả theo tab (messages, strangers, alerts, all).
     */
    public function markAllAsRead(User $user, string $tab = 'all'): void
    {
        if ($tab === 'messages' || $tab === 'all') {
            ConversationParticipant::where('user_id', $user->id)
                ->where('is_stranger', false)
                ->where('unread_count', '>', 0)
                ->update([
                    'unread_count' => 0,
                    'last_read_at' => now(),
                ]);
        }

        if ($tab === 'strangers') {
            ConversationParticipant::where('user_id', $user->id)
                ->where('is_stranger', true)
                ->where('unread_count', '>', 0)
                ->update([
                    'unread_count' => 0,
                    'last_read_at' => now(),
                ]);
        }

        if ($tab === 'alerts' || $tab === 'all') {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }
    }
}
