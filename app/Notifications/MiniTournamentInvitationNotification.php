<?php

namespace App\Notifications;

use App\Models\MiniTournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MiniTournamentInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $miniTournament;
    protected ?int $invitedBy;

    public function __construct(MiniTournament $miniTournament, ?int $invitedBy = null)
    {
        $this->miniTournament = $miniTournament;
        $this->invitedBy = $invitedBy ?? auth()->id();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable)
    {
        $tournamentName = $this->miniTournament->name ?? 'kèo đấu';

        return [
            'mini_tournament_id' => $this->miniTournament->id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        $tournamentName = $this->miniTournament->name ?? 'kèo đấu';

        return new BroadcastMessage([
            'mini_tournament_id' => $this->miniTournament->id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable): array
    {
        $tournamentName = $this->miniTournament->name ?? 'kèo đấu';

        return [
            'mini_tournament_id' => $this->miniTournament->id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
        ];
    }
}
