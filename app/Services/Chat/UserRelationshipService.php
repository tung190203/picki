<?php

namespace App\Services\Chat;

use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\Chat\Conversation;
use Illuminate\Support\Facades\Cache;

class UserRelationshipService
{
    /**
     * Kiểm tra hai người dùng có phải là "Người quen" hay không.
     * Thỏa mãn ít nhất 1 trong 3 điều kiện:
     * 1. Cùng tham gia 1 CLB (status active, membership_status joined).
     * 2. Đã từng cùng 1 kèo hoặc giải đấu và đều đã check-in.
     * 3. Người nhận đã từng chủ động trả lời (reply) trong hội thoại này.
     */
    public function isAcquaintance(int $userAId, int $userBId, ?Conversation $conversation = null): bool
    {
        if ($userAId === $userBId) {
            return true;
        }

        // Điều kiện 3: Đã từng reply trong hội thoại DM này
        if ($conversation && $conversation->isDm()) {
            $hasReplied = $conversation->participants()
                ->where('has_replied', true)
                ->count() >= 2;

            if ($hasReplied) {
                return true;
            }
        }

        $context = $this->resolveContextTag($userAId, $userBId);
        return $context['is_acquaintance'];
    }

    /**
     * Xác định nhãn ngữ cảnh quan hệ (Context Tag) hiển thị dưới tên người chat.
     */
    public function resolveContextTag(int $userAId, int $userBId): array
    {
        if ($userAId === $userBId) {
            return [
                'is_acquaintance' => true,
                'context_tag' => 'Chính bạn',
                'reason' => 'self',
            ];
        }

        $cacheKey = "user_rel_context:{$userAId}:{$userBId}";
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userAId, $userBId) {
            // 1. Kiểm tra cùng CLB
            $sharedClub = Club::query()
                ->whereHas('members', fn ($q) => $q->where('user_id', $userAId)->where('membership_status', 'joined')->where('status', 'active'))
                ->whereHas('members', fn ($q) => $q->where('user_id', $userBId)->where('membership_status', 'joined')->where('status', 'active'))
                ->select(['id', 'name'])
                ->first();

            if ($sharedClub) {
                return [
                    'is_acquaintance' => true,
                    'context_tag' => "Cùng CLB {$sharedClub->name}",
                    'reason' => 'club',
                    'target_id' => $sharedClub->id,
                ];
            }

            // 2. Kiểm tra cùng kèo (Mini Tournament) đã check-in
            $sharedMini = MiniTournament::query()
                ->whereHas('participants', fn ($q) => $q->where('user_id', $userAId)->whereNotNull('checked_in_at'))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $userBId)->whereNotNull('checked_in_at'))
                ->select(['id', 'name'])
                ->latest('id')
                ->first();

            if ($sharedMini) {
                return [
                    'is_acquaintance' => true,
                    'context_tag' => "Đã đấu chung kèo '{$sharedMini->name}'",
                    'reason' => 'mini_tournament',
                    'target_id' => $sharedMini->id,
                ];
            }

            // 3. Kiểm tra cùng giải đấu (Tournament) đã check-in
            $sharedTournament = Tournament::query()
                ->whereHas('participants', fn ($q) => $q->where('user_id', $userAId)->whereNotNull('checked_in_at'))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $userBId)->whereNotNull('checked_in_at'))
                ->select(['id', 'name'])
                ->latest('id')
                ->first();

            if ($sharedTournament) {
                return [
                    'is_acquaintance' => true,
                    'context_tag' => "Đã đấu chung giải '{$sharedTournament->name}'",
                    'reason' => 'tournament',
                    'target_id' => $sharedTournament->id,
                ];
            }

            // 4. Không có điểm chung -> Người lạ
            return [
                'is_acquaintance' => false,
                'context_tag' => 'Không có điểm chung — tìm thấy qua Tìm kiếm người chơi',
                'reason' => 'none',
                'target_id' => null,
            ];
        });
    }
}
