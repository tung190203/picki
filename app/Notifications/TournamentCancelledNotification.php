<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TournamentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $tournamentId,
        public string $tournamentName,
        public string $reason = '',
        public ?int $clubId = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tournament_id' => $this->tournamentId,
            'title' => 'Giải đấu đã bị hủy',
            'message' => "Giải đấu \"{$this->tournamentName}\" đã bị hủy."
                . ($this->reason ? " Lý do: {$this->reason}" : ''),
            'club_id' => $this->clubId,
        ];
    }
}
