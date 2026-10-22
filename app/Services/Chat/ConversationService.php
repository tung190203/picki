<?php

namespace App\Services\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ConversationUpdated;
use App\Jobs\SendPushJob;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\Chat\UserBlock;
use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationService
{
    public function __construct(
        protected UserRelationshipService $relationshipService,
        protected ChatAntiSpamService $antiSpamService
    ) {}

    /**
     * Lấy hoặc tạo mới cuộc trò chuyện 1-1 (DM) giữa 2 người dùng.
     */
    public function getOrCreateDmConversation(int $senderId, int $receiverId): Conversation
    {
        if ($senderId === $receiverId) {
            throw new Exception('Không thể tạo cuộc trò chuyện với chính mình');
        }

        $receiver = User::find($receiverId);
        if (!$receiver || $receiver->is_guest) {
            throw new Exception('Thành viên ảo không có tài khoản đăng nhập và không thể nhận tin nhắn');
        }

        // 1. Tìm cuộc trò chuyện DM đã có sẵn
        $conversation = Conversation::where('type', Conversation::TYPE_DM)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $senderId))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $receiverId))
            ->first();

        if ($conversation) {
            return $conversation->load(['participants.user', 'lastMessage']);
        }

        // 3. Nếu chưa có -> Tạo mới
        return DB::transaction(function () use ($senderId, $receiverId) {
            $isAcquaintance = $this->relationshipService->isAcquaintance($senderId, $receiverId);

            $conversation = Conversation::create([
                'type' => Conversation::TYPE_DM,
            ]);

            // Người gửi: luôn không xem đối phương là người lạ, đã reply
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $senderId,
                'role' => 'member',
                'is_stranger' => false,
                'has_replied' => true,
                'unread_count' => 0,
            ]);

            // Người nhận: Nếu không phải người quen thì xếp vào mục "Người lạ"
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $receiverId,
                'role' => 'member',
                'is_stranger' => !$isAcquaintance,
                'has_replied' => false,
                'unread_count' => 0,
            ]);

            return $conversation->load(['participants.user']);
        });
    }

    /**
     * Gửi tin nhắn vào cuộc trò chuyện.
     */
    public function sendMessage(Conversation $conversation, int $senderId, array $data): ChatMessage
    {
        // 1. Kiểm tra quyền thành viên
        $senderParticipant = $conversation->participants()->where('user_id', $senderId)->first();
        if (!$senderParticipant) {
            throw new Exception('Bạn không thuộc cuộc trò chuyện này');
        }

        // 2. Nếu là DM: Kiểm tra chặn & Giới hạn spam 10 tin/ngày
        if ($conversation->isDm()) {
            $receiverParticipant = $conversation->participants()->where('user_id', '!=', $senderId)->first();
            if (!$receiverParticipant) {
                throw new Exception('Không tìm thấy người nhận tin nhắn');
            }

            $receiverId = $receiverParticipant->user_id;

            if (UserBlock::isBlocked($senderId, $receiverId)) {
                throw new Exception('Không thể gửi tin nhắn cho người dùng này do đã bị chặn');
            }

            // Nếu người nhận vẫn đang xem người gửi là "Người lạ" (chưa từng reply)
            if ($receiverParticipant->is_stranger) {
                if (!$this->antiSpamService->canSendToStranger($senderId)) {
                    throw new Exception('Bạn đã gửi 10/10 tin nhắn tới người lạ hôm nay. Giới hạn sẽ reset lúc 00:00.');
                }
            }
        }

        // 3. Kiểm tra tính hợp lệ khi gửi thẻ (Kèo, Giải đấu, Người chơi, CLB)
        $this->validateCardSharing($senderId, $data['type'] ?? '', $data['metadata'] ?? []);

        $message = DB::transaction(function () use ($conversation, $senderId, $senderParticipant, $data) {
            // 4. Tạo tin nhắn
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'user_id' => $senderId,
                'type' => $data['type'] ?? ChatMessage::TYPE_TEXT,
                'content' => $data['content'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // 4. Cập nhật hội thoại
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
                'is_archived' => false,
            ]);

            // 5. Cập nhật trạng thái người gửi
            $senderParticipant->update([
                'has_replied' => true,
                'last_read_message_id' => $message->id,
                'last_read_at' => now(),
                'unread_count' => 0,
            ]);

            // 6. Xử lý logic chuyển đổi "Người lạ" -> "Inbox chính" vĩnh viễn khi người nhận reply lần đầu
            if ($conversation->isDm()) {
                $otherParticipant = $conversation->participants()->where('user_id', '!=', $senderId)->first();

                if ($otherParticipant) {
                    // Nếu người gửi hiện tại từng có cờ is_stranger = true (tức họ là người nhận trước đó nay bấm reply)
                    if ($senderParticipant->is_stranger) {
                        $senderParticipant->update(['is_stranger' => false]);
                        $otherParticipant->update(['is_stranger' => false]);
                    }

                    // Tăng unread count cho người nhận
                    $otherParticipant->increment('unread_count');

                    // Nếu người nhận vẫn đang xem cuộc trò chuyện này là từ người lạ, ghi nhận số lượng gửi trong ngày
                    if ($otherParticipant->is_stranger) {
                        $this->antiSpamService->recordStrangerMessageSent($senderId);
                    }
                }
            } else {
                // Với nhóm: tăng unread_count cho tất cả thành viên khác
                $conversation->participants()
                    ->where('user_id', '!=', $senderId)
                    ->increment('unread_count');
            }

            return $message->load('user');
        });

        // 7. Realtime broadcasting & Push Notifications
        $this->dispatchRealtimeAndNotifications($conversation, $message, $senderId);

        return $message;
    }

    /**
     * Phát sự kiện realtime qua Socket & gửi FCM push notification đến các thành viên khác.
     */
    protected function dispatchRealtimeAndNotifications(Conversation $conversation, ChatMessage $message, int $senderId): void
    {
        try {
            // Broadcast tin nhắn mới tới room chat
            broadcast(new ChatMessageSent($message, $conversation->id))->toOthers();

            // Đồng bộ ngược lại sang tab Thảo luận giải đấu/kèo đấu nếu có
            if ($conversation->type === Conversation::TYPE_TOURNAMENT && $conversation->target_id) {
                try {
                    $tMessage = \App\Models\TournamentMessage::create([
                        'tournament_id' => $conversation->target_id,
                        'user_id' => $senderId,
                        'type' => in_array($message->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $message->type : 'text',
                        'content' => $message->content,
                        'meta' => $message->metadata,
                    ])->load(['user', 'tournament']);

                    broadcast(new \App\Events\TournamentMessageSent(new \App\Http\Resources\MessageResource($tMessage)))->toOthers();
                } catch (\Throwable $te) {
                    Log::warning('Error broadcasting TournamentMessageSent from Inbox: ' . $te->getMessage());
                }
            } elseif ($conversation->type === Conversation::TYPE_MINI_TOURNAMENT && $conversation->target_id) {
                try {
                    $mMessage = \App\Models\MiniTournamentMessage::create([
                        'mini_tournament_id' => $conversation->target_id,
                        'user_id' => $senderId,
                        'type' => in_array($message->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $message->type : 'text',
                        'content' => $message->content,
                        'meta' => $message->metadata,
                    ])->load(['user', 'tournament']);

                    broadcast(new \App\Events\MiniTournamentMessageSent(new \App\Http\Resources\MessageResource($mMessage)))->toOthers();
                } catch (\Throwable $me) {
                    Log::warning('Error broadcasting MiniTournamentMessageSent from Inbox: ' . $me->getMessage());
                }
            }

            // Cập nhật realtime cho Inbox & gửi Push Notification
            $freshConversation = $conversation->fresh(['participants.user', 'lastMessage.sender']);
            if (!$freshConversation) {
                return;
            }

            $otherParticipants = $freshConversation->participants->where('user_id', '!=', $senderId);
            $sender = $message->user ?? User::find($senderId);
            $senderName = $sender?->full_name ?? 'Một người dùng';
            $title = $freshConversation->isDm() ? $senderName : "[{$freshConversation->title}] {$senderName}";

            $snippet = match ($message->type) {
                ChatMessage::TYPE_TEXT => $message->content ?? '',
                ChatMessage::TYPE_CARD_MINI_TOURNAMENT, 'card_match' => '[Thẻ Kèo đấu]',
                ChatMessage::TYPE_CARD_TOURNAMENT => '[Thẻ Giải đấu]',
                ChatMessage::TYPE_CARD_PLAYER => '[Thẻ Người chơi]',
                ChatMessage::TYPE_CARD_CLUB => '[Thẻ CLB]',
                ChatMessage::TYPE_IMAGE => '[Hình ảnh]',
                ChatMessage::TYPE_VOICE => '[Tin nhắn thoại]',
                ChatMessage::TYPE_FILE => '[Tập tin]',
                default => '[Tin nhắn mới]',
            };

            foreach ($otherParticipants as $participant) {
                // Cập nhật realtime cho Inbox của người nhận
                broadcast(new ConversationUpdated($freshConversation, $participant->user_id, (int) $participant->unread_count));

                // Gửi FCM Push nếu không tắt thông báo (is_muted = false)
                if (!$participant->is_muted) {
                    $fcmData = [
                        'type' => 'CHAT_MESSAGE',
                        'conversation_id' => (string) $conversation->id,
                        'conversation_type' => (string) $conversation->type,
                        'is_stranger' => $participant->is_stranger ? '1' : '0',
                        'action' => $participant->is_stranger ? 'open_strangers_box' : 'open_chat',
                        'message_id' => (string) $message->id,
                    ];

                    SendPushJob::dispatch($participant->user_id, $title, $snippet, $fcmData);
                }
            }

            // Cập nhật realtime cho người gửi
            broadcast(new ConversationUpdated($freshConversation, $senderId, 0));
        } catch (\Throwable $e) {
            Log::warning('Error dispatching chat realtime/push: ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu cuộc trò chuyện đã đọc cho một người dùng.
     */
    public function markAsRead(Conversation $conversation, int $userId): void
    {
        $participant = $conversation->participants()->where('user_id', $userId)->first();
        if ($participant) {
            $lastMessageId = (int) ($conversation->last_message_id ?? 0);
            $participant->update([
                'last_read_message_id' => $lastMessageId > 0 ? $lastMessageId : $participant->last_read_message_id,
                'last_read_at' => now(),
                'unread_count' => 0,
            ]);

            if ($lastMessageId > 0) {
                broadcast(new \App\Events\Chat\MessagesRead($conversation->id, $userId, $lastMessageId))->toOthers();
            }

            // Realtime cập nhật trạng thái hội thoại (unread_count = 0) cho chính người đọc
            $freshConversation = $conversation->fresh(['participants.user', 'lastMessage.sender']);
            if ($freshConversation) {
                broadcast(new ConversationUpdated($freshConversation, $userId, 0));
            }
        }
    }

    /**
     * Xác thực quyền chia sẻ thẻ:
     * - Kèo & Giải đấu: nếu private thì người gửi bắt buộc phải đã tham gia hoặc tạo.
     * - Người dùng: không được gửi user private/banned/guest; nếu friend-only thì phải quen biết/cùng tham gia.
     * - CLB: người gửi phải là thành viên active của CLB.
     */
    protected function validateCardSharing(int $senderId, string $type, ?array $metadata): void
    {
        if (empty($metadata['target_id'])) {
            return;
        }

        if ($type === ChatMessage::TYPE_CARD_MINI_TOURNAMENT || $type === 'card_match') {
            $match = MiniTournament::find($targetId);
            if (!$match) {
                throw new Exception('Kèo đấu không tồn tại hoặc đã bị xóa');
            }
            if ($match->is_private) {
                $isCreator = (int) $match->created_by === $senderId;
                $hasJoined = $match->participants()->where('user_id', $senderId)->exists();
                if (!$isCreator && !$hasJoined) {
                    throw new Exception('Không thể chia sẻ kèo đấu riêng tư mà bạn chưa tham gia');
                }
            }
        } elseif ($type === ChatMessage::TYPE_CARD_TOURNAMENT) {
            $tournament = Tournament::find($targetId);
            if (!$tournament) {
                throw new Exception('Giải đấu không tồn tại hoặc đã bị xóa');
            }
            if ($tournament->is_private) {
                $isCreator = (int) $tournament->created_by === $senderId;
                $hasJoined = $tournament->participants()->where('user_id', $senderId)->exists();
                if (!$isCreator && !$hasJoined) {
                    throw new Exception('Không thể chia sẻ giải đấu riêng tư mà bạn chưa tham gia');
                }
            }
        } elseif ($type === ChatMessage::TYPE_CARD_PLAYER) {
            $player = User::find($targetId);
            if (!$player || $player->is_banned || $player->is_guest) {
                throw new Exception('Người chơi không tồn tại hoặc tài khoản không khả dụng');
            }
            if ($player->visibility === 'private') {
                throw new Exception('Không thể chia sẻ người dùng đã cài đặt hồ sơ riêng tư');
            }
            if ($player->visibility === 'friend-only') {
                $isAcquaintance = $this->relationshipService->isAcquaintance($senderId, $player->id);
                if (!$isAcquaintance) {
                    throw new Exception('Người dùng này chỉ cho phép người quen/bạn bè chia sẻ');
                }
            }
        } elseif ($type === ChatMessage::TYPE_CARD_CLUB) {
            $club = Club::find($targetId);
            if (!$club) {
                throw new Exception('CLB không tồn tại hoặc đã bị xóa');
            }
            $isMember = $club->members()
                ->where('user_id', $senderId)
                ->where('membership_status', 'joined')
                ->where('status', 'active')
                ->exists();
            if (!$isMember) {
                throw new Exception('Bạn chỉ có thể chia sẻ các CLB mà mình đã tham gia');
            }
        }
    }
}
