<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Events\ClubChatMessageSent;
use App\Helpers\ResponseHelper;
use App\Models\Club\Club;
use App\Models\Club\ClubChatConversation;
use App\Models\Club\ClubChatMessage;
use App\Http\Resources\Club\ClubChatConversationResource;
use App\Http\Resources\Club\ClubChatMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubChatController extends Controller
{
    /**
     * GET /clubs/{clubId}/conversation
     * Lấy (hoặc tạo) conversation chung của CLB.
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
     * GET /clubs/{clubId}/messages
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
     * POST /clubs/{clubId}/messages
     */
    public function store(Request $request, int $clubId): JsonResponse
    {
        $userId = Auth::id();
        if (!$this->isClubMember($clubId, $userId)) {
            return ResponseHelper::error('Bạn không thuộc CLB này', 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'type' => 'nullable|string|in:text',
        ]);

        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('Không tìm thấy CLB', 404);
        }

        $conv = ClubChatConversation::forClub($clubId, $club->name);

        $message = ClubChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id' => $userId,
            'content' => $validated['content'],
            'type' => $validated['type'] ?? 'text',
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
