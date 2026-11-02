<?php

namespace App\Observers;

use App\Models\Participant;
use App\Models\Tournament;
use App\Services\Club\ClubGuestEventService;
use App\Services\Club\UserAchievementRecorder;
use Illuminate\Support\Facades\Log;

class TournamentObserver
{
    public function __construct(
        protected ClubGuestEventService $guestEventService,
        protected UserAchievementRecorder $achievementRecorder,
    ) {}

    /**
     * Khi tournament chuyển sang CLOSED, cập nhật club_guests cho
     * tất cả participant là user đã đăng ký, đồng thời ghi cup
     * vào user_achievements.
     *
     * BACKUP: Đảm bảo tất cả participants đều được thêm vào club_guests,
     * kể cả những user đăng ký sau khi giải bắt đầu (không trigger được ở ParticipantObserver).
     */
    public function updated(Tournament $tournament): void
    {
        if (!$tournament->wasChanged('status')) {
            return;
        }
        // So sánh với int constant (CLOSED = 3) — trước đây so sánh với
        // enum string 'finished' nên luôn false và observer không bao giờ chạy.
        if ((int) $tournament->status !== Tournament::CLOSED) {
            return;
        }

        // Ghi cup vào bảng user_achievements (idempotent).
        // KHÔNG phụ thuộc club_id: giải ngoài CLB vẫn được ghi cho mọi CLB mà
        // user đã từng là member (logic nằm trong recorder). Trước đây
        // `if (!$clubId) return;` chặn luôn → cup của giải không thuộc CLB
        // bị mất vĩnh viễn.
        try {
            $this->achievementRecorder->recordForTournament($tournament);
        } catch (\Exception $e) {
            Log::error('TournamentObserver: Failed to record achievement', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
        }

        $clubId = $tournament->club_id;
        if (!$clubId) {
            return;
        }

        try {
            $participantUserIds = Participant::where('tournament_id', $tournament->id)
                ->whereNotNull('user_id')
                ->pluck('user_id');
            // Dùng ClubGuestEventService với eventTime = start_date
            $this->guestEventService->upsertFromEvent($clubId, $participantUserIds, $tournament->start_date);
        } catch (\Exception $e) {
            Log::error('TournamentObserver: Failed to update club_guests', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
