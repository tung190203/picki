<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;

class ChatCardResolverService
{
    /**
     * Phân giải dữ liệu động theo thời gian thực (Real-time Resolver) cho các thẻ nhúng.
     */
    public function resolveCard(string $type, ?array $metadata): ?array
    {
        $targetId = $metadata['target_id'] ?? null;
        if (!$targetId) {
            return null;
        }

        try {
            return match ($type) {
                ChatMessage::TYPE_CARD_MINI_TOURNAMENT, 'card_match' => $this->resolveMatchCard((int) $targetId),
                ChatMessage::TYPE_CARD_TOURNAMENT => $this->resolveTournamentCard((int) $targetId),
                ChatMessage::TYPE_CARD_PLAYER => $this->resolvePlayerCard((int) $targetId),
                ChatMessage::TYPE_CARD_CLUB => $this->resolveClubCard((int) $targetId),
                default => null,
            };
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("resolveCard failed for type {$type}, targetId {$targetId}: " . $e->getMessage());
            return [
                'id' => (int) $targetId,
                'is_deleted' => true,
                'title' => 'Nội dung không khả dụng',
            ];
        }
    }

    protected function resolveMatchCard(int $miniTournamentId): ?array
    {
        $mini = MiniTournament::with(['competitionLocation'])->find($miniTournamentId);
        if (!$mini) {
            return [
                'id' => $miniTournamentId,
                'is_deleted' => true,
                'title' => 'Kèo đấu không tồn tại hoặc đã bị xóa',
            ];
        }

        $confirmedCount = $mini->participants()->where('is_confirmed', 1)->count();
        $maxSlots = $mini->max_players ?? 0;
        $slotsText = $maxSlots > 0 ? "{$confirmedCount}/{$maxSlots} người tham gia" : "{$confirmedCount} người tham gia";

        return [
            'id' => $mini->id,
            'is_deleted' => false,
            'title' => $mini->name,
            'poster_url' => $mini->poster ?? null,
            'start_time' => optional($mini->start_time)->toDateTimeString(),
            'end_time' => optional($mini->end_time)->toDateTimeString(),
            'location_name' => $mini->competitionLocation?->name ?? $mini->address ?? 'Chưa xác định',
            'slots_text' => $slotsText,
            'confirmed_count' => $confirmedCount,
            'max_slots' => $maxSlots,
            'status' => $mini->status,
            'status_text' => $mini->status_text ?? 'Đang diễn ra',
            'route' => "/mini-tournament-detail/{$mini->id}",
        ];
    }

    protected function resolveTournamentCard(int $tournamentId): ?array
    {
        $tour = Tournament::with('competitionLocation')->find($tournamentId);
        if (!$tour) {
            return [
                'id' => $tournamentId,
                'is_deleted' => true,
                'title' => 'Giải đấu không tồn tại hoặc đã bị xóa',
            ];
        }

        return [
            'id' => $tour->id,
            'is_deleted' => false,
            'title' => $tour->name,
            'poster_url' => $tour->poster,
            'start_date' => optional($tour->start_date)->toDateString(),
            'end_date' => optional($tour->end_date)->toDateString(),
            'location_name' => $tour->competitionLocation?->name ?? 'Chưa xác định',
            'status' => $tour->status,
            'status_text' => $tour->status_text ?? 'Đang diễn ra',
            'route' => "/tournament-detail/{$tour->id}",
        ];
    }

    protected function resolvePlayerCard(int $userId): ?array
    {
        $user = User::find($userId);
        if (!$user) {
            return [
                'id' => $userId,
                'is_deleted' => true,
                'title' => 'Người chơi không tồn tại',
            ];
        }

        $rating = $user->vnduprScores()->max('score_value') ?? $user->self_score ?? null;
        if ($rating !== null) {
            $rating = (float) $rating;
        }

        return [
            'id' => $user->id,
            'is_deleted' => false,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'rating' => $rating,
            'rating_text' => $rating ? "Điểm trình: {$rating}" : 'Chưa có điểm trình',
            'route' => "/profile/{$user->id}",
        ];
    }

    protected function resolveClubCard(int $clubId): ?array
    {
        $club = Club::find($clubId);
        if (!$club) {
            return [
                'id' => $clubId,
                'is_deleted' => true,
                'title' => 'CLB không tồn tại hoặc đã bị xóa',
            ];
        }

        $memberCount = $club->members()
            ->where('membership_status', 'joined')
            ->where('status', 'active')
            ->count();

        return [
            'id' => $club->id,
            'is_deleted' => false,
            'name' => $club->name,
            'avatar_url' => $club->logo_url,
            'member_count' => $memberCount,
            'member_count_text' => "{$memberCount} thành viên",
            'route' => "/clubs/{$club->id}",
        ];
    }
}
