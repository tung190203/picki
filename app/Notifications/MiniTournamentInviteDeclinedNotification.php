<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentInviteDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected MiniParticipant $participant;

    public function __construct(MiniParticipant $participant)
    {
        $this->participant = $participant;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        $declinerName = $this->participant->user?->full_name ?? 'Một người chơi';
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'participant_id' => $this->participant->id,
            'title' => 'Lời mời bị từ chối',
            'message' => "{$declinerName} đã từ chối lời mời tham gia kèo đấu \"{$tournamentName}\".",
            'declined_user_id' => $this->participant->user_id,
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable) + [
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
