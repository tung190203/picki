<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MiniTournamentJoinRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $participant;

    public function __construct(MiniParticipant $participant)
    {
        $this->participant = $participant;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast']; // vừa lưu DB vừa realtime
    }

    public function toDatabase($notifiable): array
    {
        $requesterName = $this->participant->user?->full_name ?? 'Một người chơi';
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'participant_id' => $this->participant->id,
            'title' => 'Yêu cầu tham gia mới',
            'message' => "{$requesterName} vừa gửi yêu cầu tham gia kèo đấu \"{$tournamentName}\".",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => $this->toDatabase($notifiable)
        ];
    }

    public function toArray($notifiable): array
    {
        $requesterName = $this->participant->user?->full_name ?? 'Một người chơi';
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'participant_id' => $this->participant->id,
            'title' => 'Yêu cầu tham gia mới',
            'message' => "{$requesterName} vừa gửi yêu cầu tham gia kèo đấu \"{$tournamentName}\".",
        ];
    }
}
