<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $tournamentName;
    protected int $tournamentId;
    protected ?int $cancelledBy;

    public function __construct(string $tournamentName, int $tournamentId, ?int $cancelledBy = null)
    {
        $this->tournamentName = $tournamentName;
        $this->tournamentId = $tournamentId;
        $this->cancelledBy = $cancelledBy;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'mini_tournament_id' => $this->tournamentId,
            'title' => 'Kèo đấu đã bị hủy',
            'message' => "Kèo đấu \"{$this->tournamentName}\" đã bị chủ kèo hủy.",
            'cancelled_by' => $this->cancelledBy,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'mini_tournament_id' => $this->tournamentId,
            'title' => 'Kèo đấu đã bị hủy',
            'message' => "Kèo đấu \"{$this->tournamentName}\" đã bị chủ kèo hủy.",
            'cancelled_by' => $this->cancelledBy,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'mini_tournament_id' => $this->tournamentId,
            'title' => 'Kèo đấu đã bị hủy',
            'message' => "Kèo đấu \"{$this->tournamentName}\" đã bị chủ kèo hủy.",
            'cancelled_by' => $this->cancelledBy,
        ];
    }
}
