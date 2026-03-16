<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentMemberJoinedNotification extends Notification implements ShouldQueue
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
        $memberName = $this->participant->user?->full_name ?? 'Một người chơi';
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => 'Thành viên mới tham gia',
            'message' => "{$memberName} đã tham gia kèo đấu \"{$tournamentName}\".",
            'joined_user_id' => $this->participant->user_id,
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

