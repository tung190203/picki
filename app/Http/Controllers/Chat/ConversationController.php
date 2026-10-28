<?php

namespace App\Http\Controllers\Chat;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Http\Resources\Chat\ConversationResource;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\Conversation;
use App\Services\Chat\ConversationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * Bắt đầu hoặc lấy cuộc trò chuyện 1-1 (DM) với một người dùng.
     */
    public function startDm(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
        ]);

        $currentUserId = Auth::id();
        $receiverId = (int) $request->receiver_id;

        try {
            $conversation = $this->conversationService->getOrCreateDmConversation($currentUserId, $receiverId);
            return ResponseHelper::success(
                new ConversationResource($conversation),
                'Lấy cuộc trò chuyện thành công'
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * Bắt đầu hoặc lấy cuộc trò chuyện nhóm (Club, Mini-tournament, Tournament).
     */
    public function getGroupConversation(Request $request, \App\Services\Chat\GroupChatSyncService $groupSyncService)
    {
        $validated = $request->validate([
            'type' => 'required|in:club,mini_tournament,tournament',
            'target_id' => 'required|integer',
        ]);

        $type = $validated['type'];
        $targetId = (int) $validated['target_id'];

        $conversation = match ($type) {
            'club' => ($club = \App\Models\Club\Club::find($targetId)) ? $groupSyncService->syncClub($club) : null,
            'mini_tournament' => ($mt = \App\Models\MiniTournament::find($targetId)) ? $groupSyncService->syncMiniTournament($mt) : null,
            'tournament' => ($t = \App\Models\Tournament::find($targetId)) ? $groupSyncService->syncTournament($t) : null,
        };

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy Kèo/Giải/CLB tương ứng', 404);
        }

        return ResponseHelper::success(
            new ConversationResource($conversation),
            'Lấy cuộc trò chuyện nhóm thành công'
        );
    }

    /**
     * Lấy chi tiết thông tin một cuộc trò chuyện.
     */
    public function show(int $id)
    {
        $currentUserId = Auth::id();

        $conversation = Conversation::with(['participants.user', 'lastMessage'])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $currentUserId))
            ->find($id);

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy cuộc trò chuyện hoặc bạn không có quyền xem', 404);
        }

        return ResponseHelper::success(
            new ConversationResource($conversation),
            'Lấy thông tin cuộc trò chuyện thành công'
        );
    }

    /**
     * Lấy danh sách tin nhắn trong cuộc trò chuyện (kèm phân trang).
     */
    public function getMessages(Request $request, int $id)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $currentUserId = Auth::id();

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $currentUserId))
            ->find($id);

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy cuộc trò chuyện hoặc bạn không có quyền truy cập', 404);
        }

        $perPage = (int) ($request->per_page ?? 20);

        $messages = ChatMessage::where('conversation_id', $id)
            ->with(['user', 'conversation.participants'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Đánh dấu đã đọc cuộc trò chuyện cho user hiện tại
        $this->conversationService->markAsRead($conversation, $currentUserId);

        $meta = [
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
        ];

        return ResponseHelper::success(
            ChatMessageResource::collection($messages->reverse()->values()),
            'Lấy tin nhắn thành công',
            200,
            $meta
        );
    }

    /**
     * Gửi tin nhắn vào cuộc trò chuyện.
     */
    public function sendMessage(Request $request, int $id)
    {
        $request->validate([
            'type' => 'required|in:text,image,voice,file,card_mini_tournament,card_match,card_tournament,card_player,card_club',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // max 10MB
            'metadata' => 'nullable|array',
        ]);

        $currentUserId = Auth::id();

        $conversation = Conversation::with('participants')->find($id);

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy cuộc trò chuyện', 404);
        }

        $content = $request->input('content');
        $metadata = $request->input('metadata', []);
        if (!is_array($metadata)) {
            $metadata = json_decode((string) $metadata, true) ?: [];
        }
        if (empty($metadata['target_id']) && $request->filled('target_id')) {
            $metadata['target_id'] = (int) $request->input('target_id');
        }

        // Xử lý upload file nếu có
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('chat', 'public');
            $content = $path;
            $metadata = array_merge($metadata, [
                'filename' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
                'mime_type' => $request->file('file')->getMimeType(),
            ]);
        }

        try {
            $message = $this->conversationService->sendMessage($conversation, $currentUserId, [
                'type' => $request->type,
                'content' => $content,
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);

            return ResponseHelper::success(
                new ChatMessageResource($message),
                'Gửi tin nhắn thành công'
            );
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), '10/10 tin nhắn') ? 429 : 400;
            return ResponseHelper::error($e->getMessage(), $code);
        }
    }

    /**
     * Đánh dấu cuộc trò chuyện đã đọc.
     */
    public function markAsRead(int $id)
    {
        $currentUserId = Auth::id();

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $currentUserId))
            ->find($id);

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy cuộc trò chuyện', 404);
        }

        $this->conversationService->markAsRead($conversation, $currentUserId);

        return ResponseHelper::success(null, 'Đánh dấu đã đọc thành công');
    }

    /**
     * Bật/tắt thông báo cho một cuộc trò chuyện.
     * Nếu truyền is_muted (boolean) sẽ set theo giá trị đó; nếu không truyền sẽ tự động đảo trạng thái (toggle).
     */
    public function toggleMute(Request $request, int $id)
    {
        $request->validate([
            'is_muted' => 'nullable|boolean',
        ]);

        $currentUserId = Auth::id();

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $currentUserId))
            ->find($id);

        if (!$conversation) {
            return ResponseHelper::error('Không tìm thấy cuộc trò chuyện', 404);
        }

        $isMuted = $request->has('is_muted')
            ? filter_var($request->input('is_muted'), FILTER_VALIDATE_BOOLEAN)
            : null;

        try {
            $updatedConversation = $this->conversationService->toggleMute($conversation, $currentUserId, $isMuted);
            $participant = $updatedConversation->getParticipantForUser($currentUserId);
            $mutedState = (bool) ($participant?->is_muted ?? false);

            return ResponseHelper::success(
                new ConversationResource($updatedConversation),
                $mutedState ? 'Đã tắt thông báo cuộc trò chuyện' : 'Đã bật thông báo cuộc trò chuyện'
            );
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }
}
