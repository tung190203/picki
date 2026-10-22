<?php

namespace App\Services\Chat;

use App\Models\Chat\Conversation;
use App\Models\Chat\ConversationParticipant;
use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupChatSyncService
{
    /**
     * Đồng bộ nhóm chat cho một Câu lạc bộ (CLB).
     */
    public function syncClub(Club $club): Conversation
    {
        $conversation = Conversation::firstOrCreate([
            'type' => Conversation::TYPE_CLUB,
            'target_id' => $club->id,
        ], [
            'title' => 'CLB ' . $club->name,
            'avatar_url' => $club->avatar_url,
        ]);

        // Lấy danh sách thành viên hợp lệ (không bao gồm thành viên ảo)
        $validUserIds = $club->members()
            ->where('membership_status', 'joined')
            ->where('status', 'active')
            ->whereHas('user', fn ($q) => $q->where('is_guest', false))
            ->pluck('user_id');

        $this->syncParticipants($conversation, $validUserIds);

        return $conversation;
    }

    /**
     * Đồng bộ nhóm chat cho một Kèo đấu (Mini Tournament).
     */
    public function syncMiniTournament(MiniTournament $miniTournament): Conversation
    {
        $posterUrl = $miniTournament->poster;
        if ($posterUrl && !str_starts_with($posterUrl, 'http') && !str_starts_with($posterUrl, '/storage/')) {
            $posterUrl = '/storage/' . ltrim($posterUrl, '/');
        }

        $conversation = Conversation::firstOrCreate([
            'type' => Conversation::TYPE_MINI_TOURNAMENT,
            'target_id' => $miniTournament->id,
        ], [
            'title' => $miniTournament->name,
            'avatar_url' => $posterUrl,
        ]);

        $conversation->update([
            'title' => $miniTournament->name,
            'avatar_url' => $posterUrl,
        ]);

        $validUserIds = $miniTournament->all_users
            ->filter(fn ($u) => empty($u->is_guest))
            ->pluck('id');

        $this->syncParticipants($conversation, $validUserIds);

        $this->syncMiniTournamentMessagesTwoWay($conversation, $miniTournament->id);

        return $conversation;
    }

    /**
     * Đồng bộ nhóm chat cho một Giải đấu (Tournament).
     */
    public function syncTournament(Tournament $tournament): Conversation
    {
        $posterUrl = $tournament->poster;
        if ($posterUrl && !str_starts_with($posterUrl, 'http') && !str_starts_with($posterUrl, '/storage/')) {
            $posterUrl = '/storage/' . ltrim($posterUrl, '/');
        }

        $conversation = Conversation::firstOrCreate([
            'type' => Conversation::TYPE_TOURNAMENT,
            'target_id' => $tournament->id,
        ], [
            'title' => $tournament->name,
            'avatar_url' => $posterUrl,
        ]);

        $conversation->update([
            'title' => $tournament->name,
            'avatar_url' => $posterUrl,
        ]);

        $validUserIds = $tournament->all_users
            ->filter(fn ($u) => empty($u->is_guest))
            ->pluck('id');

        $this->syncParticipants($conversation, $validUserIds);

        $this->syncTournamentMessagesTwoWay($conversation, $tournament->id);

        return $conversation;
    }

    /**
     * Đồng bộ 2 chiều toàn bộ tin nhắn giải đấu giữa TournamentMessage và ChatMessage.
     */
    public function syncTournamentMessagesTwoWay(Conversation $conversation, int $tournamentId): void
    {
        $tMessages = \App\Models\TournamentMessage::where('tournament_id', $tournamentId)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($tMessages as $tMsg) {
            $exists = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)
                ->where('user_id', $tMsg->user_id)
                ->where('content', $tMsg->content)
                ->exists();

            if (!$exists) {
                \App\Models\Chat\ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $tMsg->user_id,
                    'type' => in_array($tMsg->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $tMsg->type : 'text',
                    'content' => $tMsg->content,
                    'metadata' => $tMsg->meta,
                    'created_at' => $tMsg->created_at,
                    'updated_at' => $tMsg->updated_at,
                ]);
            }
        }

        $cMessages = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($cMessages as $cMsg) {
            $exists = \App\Models\TournamentMessage::where('tournament_id', $tournamentId)
                ->where('user_id', $cMsg->user_id)
                ->where('content', $cMsg->content)
                ->exists();

            if (!$exists) {
                \App\Models\TournamentMessage::create([
                    'tournament_id' => $tournamentId,
                    'user_id' => $cMsg->user_id,
                    'type' => in_array($cMsg->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $cMsg->type : 'text',
                    'content' => $cMsg->content,
                    'meta' => $cMsg->metadata,
                    'created_at' => $cMsg->created_at,
                    'updated_at' => $cMsg->updated_at,
                ]);
            }
        }

        $latestChatMsg = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)->latest('id')->first();
        if ($latestChatMsg) {
            $conversation->update([
                'last_message_id' => $latestChatMsg->id,
                'last_message_at' => $latestChatMsg->created_at ?? now(),
            ]);
        }
    }

    /**
     * Đồng bộ 2 chiều toàn bộ tin nhắn kèo đấu giữa MiniTournamentMessage và ChatMessage.
     */
    public function syncMiniTournamentMessagesTwoWay(Conversation $conversation, int $miniTournamentId): void
    {
        $miniMessages = \App\Models\MiniTournamentMessage::where('mini_tournament_id', $miniTournamentId)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($miniMessages as $mMsg) {
            $exists = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)
                ->where('user_id', $mMsg->user_id)
                ->where('content', $mMsg->content)
                ->exists();

            if (!$exists) {
                \App\Models\Chat\ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $mMsg->user_id,
                    'type' => in_array($mMsg->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $mMsg->type : 'text',
                    'content' => $mMsg->content,
                    'metadata' => $mMsg->meta,
                    'created_at' => $mMsg->created_at,
                    'updated_at' => $mMsg->updated_at,
                ]);
            }
        }

        $cMessages = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($cMessages as $cMsg) {
            $exists = \App\Models\MiniTournamentMessage::where('mini_tournament_id', $miniTournamentId)
                ->where('user_id', $cMsg->user_id)
                ->where('content', $cMsg->content)
                ->exists();

            if (!$exists) {
                \App\Models\MiniTournamentMessage::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'user_id' => $cMsg->user_id,
                    'type' => in_array($cMsg->type, ['text', 'image', 'voice', 'file', 'emoji']) ? $cMsg->type : 'text',
                    'content' => $cMsg->content,
                    'meta' => $cMsg->metadata,
                    'created_at' => $cMsg->created_at,
                    'updated_at' => $cMsg->updated_at,
                ]);
            }
        }

        $latestChatMsg = \App\Models\Chat\ChatMessage::where('conversation_id', $conversation->id)->latest('id')->first();
        if ($latestChatMsg) {
            $conversation->update([
                'last_message_id' => $latestChatMsg->id,
                'last_message_at' => $latestChatMsg->created_at ?? now(),
            ]);
        }
    }

    /**
     * Thêm một user vào nhóm chat theo đối tượng (Kèo/Giải/CLB).
     */
    public function addUserToGroup(string $type, int $targetId, int $userId, string $role = 'member'): ?ConversationParticipant
    {
        $user = User::find($userId);
        if (!$user || $user->is_guest) {
            // Thành viên ảo không được thêm vào nhóm chat
            return null;
        }

        $conversation = Conversation::firstOrCreate([
            'type' => $type,
            'target_id' => $targetId,
        ]);

        return ConversationParticipant::firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
        ], [
            'role' => $role,
            'is_stranger' => false,
            'has_replied' => true,
        ]);
    }

    /**
     * Gỡ một user khỏi nhóm chat khi bị hủy/rời Kèo/Giải/CLB.
     */
    public function removeUserFromGroup(string $type, int $targetId, int $userId): void
    {
        $conversation = Conversation::where('type', $type)
            ->where('target_id', $targetId)
            ->first();

        if ($conversation) {
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->delete();
        }
    }

    /**
     * Đồng bộ danh sách user_id vào conversation_participants.
     */
    protected function syncParticipants(Conversation $conversation, Collection $validUserIds): void
    {
        DB::transaction(function () use ($conversation, $validUserIds) {
            // 1. Xóa các user không còn hợp lệ
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->whereNotIn('user_id', $validUserIds)
                ->delete();

            // 2. Thêm mới các user chưa có trong nhóm
            $existingUserIds = ConversationParticipant::where('conversation_id', $conversation->id)
                ->pluck('user_id')
                ->toArray();

            $newIds = $validUserIds->diff($existingUserIds);

            $rows = [];
            $now = now();
            foreach ($newIds as $userId) {
                $rows[] = [
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'is_stranger' => false,
                    'has_replied' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                ConversationParticipant::insert($rows);
            }
        });
    }
}
