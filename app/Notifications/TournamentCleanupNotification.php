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

    private function getVietnameseTypeLabel(string $type): string
    {
        return match ($type) {
            'mini-tournament' => 'Kèo đấu',
            'tournament' => 'Giải đấu',
            default => $type,
        };
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabel = $this->getVietnameseTypeLabel($this->tournamentType);

        return [
            'type' => self::TYPE,
            'action' => 'cleanup',
            'tournament_type' => $this->tournamentType,
            'tournament_id' => $this->tournamentId,
            'title' => $typeLabel . ' đã được xóa tự động.',
            'message' => $typeLabel . " \"{$this->tournamentName}\" đã được xóa tự động. Lý do: {$this->reason}",
            'club_id' => $this->clubId,
        ];
    }
}
