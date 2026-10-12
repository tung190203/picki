<?php

namespace App\Services\Club;

use App\Models\Club\ClubGuest;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Trigger club_guests dựa trên start_time của giải.
 * Chỉ chạy 1 lần khi giải đến giờ bắt đầu - dùng cờ processed để tránh duplicate.
 */
class ClubGuestEventService
{
    /**
     * Upsert user vào club_guests với eventTime = start_time.
     * Idempotent: user đã tồn tại → tăng play_count; user mới → tạo mới.
     *
     * @return int số user mới được tạo
     */
    public function upsertFromEvent(int $clubId, Collection $participantUserIds, $startTime = null): int
    {
        if ($participantUserIds->isEmpty() || !$clubId) {
            return 0;
        }

        $eventTime = $startTime ?? now();
        $existingUserIds = ClubGuest::where('club_id', $clubId)
            ->whereIn('user_id', $participantUserIds)
            ->pluck('user_id')
            ->all();

        if (!empty($existingUserIds)) {
            ClubGuest::where('club_id', $clubId)
                ->whereIn('user_id', $existingUserIds)
                ->increment('play_count');
            ClubGuest::where('club_id', $clubId)
                ->whereIn('user_id', $existingUserIds)
                ->update(['last_played_at' => $eventTime, 'updated_at' => now()]);
        }

        $newUserIds = array_diff($participantUserIds->all(), $existingUserIds);
        if (empty($newUserIds)) {
            return 0;
        }

        $rows = [];
        foreach ($newUserIds as $userId) {
            $rows[] = [
                'club_id' => $clubId,
                'user_id' => $userId,
                'play_count' => 1,
                'first_played_at' => $eventTime,
                'last_played_at' => $eventTime,
                'is_invited' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ClubGuest::insert($rows);

        return count($rows);
    }

    /**
     * Trigger club_guests cho 1 mini-tournament.
     * Lấy tất cả user đã đăng ký (cả confirmed) → thêm vào club_guests.
     * Idempotent nhờ upsertFromEvent.
     */
    public function triggerMiniTournament(MiniTournament $miniTournament): int
    {
        $clubId = $miniTournament->club_id;
        if (!$clubId || !$miniTournament->start_time) {
            return 0;
        }

        $participantUserIds = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        return $this->upsertFromEvent($clubId, $participantUserIds, $miniTournament->start_time);
    }

    /**
     * Trigger club_guests cho 1 tournament.
     */
    public function triggerTournament(Tournament $tournament): int
    {
        $clubId = $tournament->club_id;
        if (!$clubId || !$tournament->start_date) {
            return 0;
        }

        $participantUserIds = Participant::where('tournament_id', $tournament->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        return $this->upsertFromEvent($clubId, $participantUserIds, $tournament->start_date);
    }

    /**
     * Check xem 1 giải đã trigger club_guests chưa.
     * Dựa vào cờ club_guests_processed_at (nếu có column) hoặc logic khác.
     */
    public function isProcessed(MiniTournament|Tournament $event): bool
    {
        return !is_null($event->club_guests_processed_at ?? null);
    }

    /**
     * Đánh dấu đã xử lý.
     */
    public function markProcessed(MiniTournament|tournament $event): void
    {
        if (\Schema::hasColumn($event->getTable(), 'club_guests_processed_at')) {
            $event->forceFill(['club_guests_processed_at' => now()])->save();
        }
    }
}
