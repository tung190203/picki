<?php

namespace App\Notifications;

use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TournamentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public string $reason = ''
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tournament_id' => $this->tournament->id,
            'title' => 'Giải đấu đã bị hủy',
            'message' => "Giải đấu \"{$this->tournament->name}\" đã bị hủy."
                . ($this->reason ? " Lý do: {$this->reason}" : ''),
            'club_id' => $this->tournament->club_id,
        ];
    }
}
