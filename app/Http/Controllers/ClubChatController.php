<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Events\ClubChatMessageRead;
use App\Events\ClubChatMessageSent;
use App\Helpers\ResponseHelper;
use App\Http\Resources\Club\ClubChatConversationResource;
use App\Http\Resources\Club\ClubChatMessageResource;
use App\Models\Club\Club;
use App\Models\Club\ClubChatConversation;
use App\Models\Club\ClubChatMessage;
use App\Models\Club\ClubChatRead;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClubChatController extends Controller
{
    /**
     * GET /clubs/{clubId}/chat/conversation
     */
    public function conversation(int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('Không tìm thấy CLB', 404);
        }

        $conv = ClubChatConversation::forClub($clubId, $club->name);
        $conv->load('lastMessage');

        return ResponseHelper::success(new ClubChatConversationResource($conv));
    }

    /**
     * GET /clubs/{clubId}/chat/messages
     */
    public function index(Request $request, int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('Không tìm thấy CLB', 404);
        }

        $conv = ClubChatConversation::forClub($clubId, $club->name);

        $perPage = min((int) $request->input('per_page', 50), 100);
        $page = max((int) $request->input('page', 1), 1);

        $messages = ClubChatMessage::with('user')
            ->where('conversation_id', $conv->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return ResponseHelper::paginated(
            ClubChatMessageResource::collection($messages)->resolve(),
            [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
            'Lấy tin nhắn thành công'
        );
    }

    /**
     * POST /clubs/{clubId}/chat/messages
     * Body:
     *   - content: string (optional if attachment_type set)
     *   - attachment_type: image|video|file|tournament
     *   - attachment_meta: array (path/url/name/size, hoặc tournament_id/title/type/start_date)
     */
    public function store(Request $request, int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:2000',
            'attachment_type' => 'nullable|string|in:image,video,file,tournament',
            'attachment_meta' => 'nullable|array',
            'attachment_meta.url' => 'required_if:attachment_type,image,video,file|string',
            'attachment_meta.path' => 'required_if:attachment_type,image,video,file|string',
            'attachment_meta.name' => 'required_if:attachment_type,file|string|max:255',
            'attachment_meta.size' => 'nullable|integer',
            'attachment_meta.mime' => 'nullable|string|max:100',
            'attachment_meta.tournament_id' => 'required_if:attachment_type,tournament|integer',
            'attachment_meta.kind' => 'required_with:attachment_meta.tournament_id|string|in:tournament,mini_tournament',
        ]);

        if (empty($validated['content']) && empty($validated['attachment_type'])) {
            return ResponseHelper::error('Tin nhắn phải có nội dung hoặc đính kèm', 422);
        }

        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('Không tìm thấy CLB', 404);
        }

        $conv = ClubChatConversation::forClub($clubId, $club->name);

        $attachmentMeta = $validated['attachment_meta'] ?? null;
        if (($validated['attachment_type'] ?? null) === 'tournament' && $attachmentMeta) {
            $kind = $attachmentMeta['kind'] ?? 'tournament';
            $hit = $kind === 'mini_tournament'
                ? MiniTournament::with('sport')->find($attachmentMeta['tournament_id'])
                : Tournament::with('sport')->find($attachmentMeta['tournament_id']);
            if (!$hit || (int) $hit->club_id !== (int) $clubId) {
                return ResponseHelper::error('Giải đấu không thuộc CLB này', 422);
            }
            $startAt = $kind === 'mini_tournament' ? $hit->start_time : $hit->start_date;
            $posterUrl = $kind === 'mini_tournament'
                ? ($hit->poster ? asset('storage/' . $hit->poster) : null)
                : $hit->poster_url;
            $isJoined = $kind === 'mini_tournament'
                ? $hit->participants()->where('user_id', $userId)->exists()
                : $hit->participants()->where('user_id', $userId)->exists();
            $attachmentMeta = [
                'kind' => $kind,
                'tournament_id' => $hit->id,
                'title' => $hit->name,
                'start_at' => $startAt?->toIso8601String(),
                'poster_url' => $posterUrl,
                'sport_name' => $hit->sport?->name,
                'status' => $hit->status,
                'status_text' => $hit->status_text ?? null,
                'has_fee' => (bool) ($hit->has_fee ?? false),
                'fee_amount' => (int) ($hit->fee_amount ?? 0),
                'is_joined' => $isJoined,
                'location_name' => $hit->competitionLocation?->name ?? null,
            ];
        }

        $message = ClubChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id' => $userId,
            'content' => $validated['content'] ?? '',
            'type' => $validated['attachment_type'] ?? 'text',
            'attachment_type' => $validated['attachment_type'] ?? null,
            'attachment_meta' => $attachmentMeta,
        ]);

        $conv->update(['last_message_id' => $message->id]);

        $message->load('user');

        broadcast(new ClubChatMessageSent($message, $clubId, $conv->id))->toOthers();

        return ResponseHelper::success(
            (new ClubChatMessageResource($message))->resolve(),
            'Gửi tin nhắn thành công',
            201
        );
    }

    /**
     * POST /clubs/{clubId}/chat/upload (multipart 'file')
     * Trả về { url, path, name, size, mime } để client dùng khi tạo message.
     */
    public function upload(Request $request, int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };

        $path = $file->store("club-chat/{$clubId}", 'public');
        $url = asset('storage/' . $path);

        return ResponseHelper::success([
            'attachment_type' => $type,
            'url' => $url,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $mime,
        ], 'Upload thành công');
    }

    /**
     * POST /clubs/{clubId}/chat/read
     */
    public function markRead(Request $request, int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $validated = $request->validate([
            'message_id' => 'required|integer|exists:club_chat_messages,id',
        ]);

        $conv = ClubChatConversation::where('club_id', $clubId)->first();
        if (!$conv) {
            return ResponseHelper::error('Không tìm thấy nhóm chat', 404);
        }

        $read = ClubChatRead::updateOrCreate(
            ['conversation_id' => $conv->id, 'user_id' => $userId],
            ['last_read_message_id' => $validated['message_id'], 'read_at' => now()]
        );

        broadcast(new ClubChatMessageRead($clubId, $conv->id, $read))->toOthers();

        return ResponseHelper::success(null, 'Đã đánh dấu đã đọc');
    }

    /**
     * GET /clubs/{clubId}/chat/reads
     */
    public function reads(int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $conv = ClubChatConversation::where('club_id', $clubId)->first();
        if (!$conv) {
            return ResponseHelper::error('Không tìm thấy nhóm chat', 404);
        }

        $reads = ClubChatRead::with('user:id,full_name,avatar_url')
            ->where('conversation_id', $conv->id)
            ->get();

        $payload = $reads->map(fn ($r) => [
            'user_id' => $r->user_id,
            'user' => $r->user ? [
                'id' => $r->user->id,
                'full_name' => $r->user->full_name,
                'avatar_url' => $r->user->avatar_url,
            ] : null,
            'last_read_message_id' => $r->last_read_message_id,
            'read_at' => $r->read_at?->toIso8601String(),
        ]);

        return ResponseHelper::success($payload, 'OK');
    }

    private function isClubMember(int $clubId, ?int $userId): bool
    {
        if (!$userId) return false;

        return \DB::table('club_members')
            ->where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('membership_status', ClubMembershipStatus::Joined->value)
            ->where('status', ClubMemberStatus::Active->value)
            ->exists();
    }
}
