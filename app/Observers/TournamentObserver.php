<?php

namespace App\Observers;

use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use App\Services\Club\ClubGuestEventService;
use Illuminate\Support\Facades\Log;

class TournamentObserver
{
    public function __construct(protected ClubGuestEventService $guestEventService) {}

    /**
     * Khi tournament chuyển sang Finished, cập nhật club_guests cho
     * tất cả participant là user đã đăng ký.
     * 
     * BACKUP: Đảm bảo tất cả participants đều được thêm vào club_guests,
     * kể cả những user đăng ký sau khi giải bắt đầu (không trigger được ở ParticipantObserver).
     */
    public function updated(Tournament $tournament): void
    {
        if (!$tournament->wasChanged('status')) {
            return;
        }
        if ((string) $tournament->status !== TournamentStatus::Finished->value) {
            return;
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
