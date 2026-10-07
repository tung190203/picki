<?php

namespace App\Observers;

use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use App\Services\Club\ClubGuestService;
use Illuminate\Support\Facades\Log;

class TournamentObserver
{
    public function __construct(protected ClubGuestService $guestService) {}

    /**
     * Khi tournament chuyển sang Finished, cập nhật club_guests cho
     * tất cả participant là user đã đăng ký.
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
            $this->guestService->upsertFromEvent($clubId, $participantUserIds);
        } catch (\Exception $e) {
            Log::error('TournamentObserver: Failed to update club_guests', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
