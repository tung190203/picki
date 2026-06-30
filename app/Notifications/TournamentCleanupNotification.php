<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TournamentCleanupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const TYPE = 'tournament_cleanup';

    public function __construct(
        public readonly string $tournamentType,
        public readonly string $tournamentName,
        public readonly string $reason = '',
        public readonly ?int $clubId = null,
        public readonly ?int $tournamentId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'action' => 'cleanup',
            'tournament_type' => $this->tournamentType,
            'tournament_id' => $this->tournamentId,
            'title' => ucfirst($this->tournamentType) . ' bi xoa do khong co nguoi tham gia',
            'message' => "{$this->tournamentType} \"{$this->tournamentName}\" da bi xoa tu dong. Ly do: {$this->reason}",
            'club_id' => $this->clubId,
        ];
    }
}
