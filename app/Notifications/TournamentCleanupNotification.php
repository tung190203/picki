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
            'title' => ucfirst($this->tournamentType) . ' đã được xóa tự động.',
            'message' => "{$this->tournamentType} \"{$this->tournamentName}\" đã được xóa tự động. Lý do: {$this->reason}",
            'club_id' => $this->clubId,
        ];
    }
}
