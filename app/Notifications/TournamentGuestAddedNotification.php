<?php

namespace App\Notifications;

use App\Models\Participant;
use App\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TournamentGuestAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Tournament $tournament,
        protected Participant $guestParticipant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $guestName = $this->guestParticipant->guest_name;
        $tournamentName = $this->tournament->name;
        $isPending = $this->guestParticipant->is_pending_confirmation;

        if ($isPending) {
            $message = "Có khách mời \"{$guestName}\" trong giải \"{$tournamentName}\" đang chờ được xác nhận (do VĐV bảo lãnh).";
        } else {
            $message = "Khách mời \"{$guestName}\" đã được thêm vào giải \"{$tournamentName}\".";
        }

        return [
            'tournament_id' => $this->tournament->id,
            'participant_id' => $this->guestParticipant->id,
            'guarantor_user_id' => $this->guestParticipant->guarantor_user_id,
            'title' => $isPending ? 'Khách mời chờ xác nhận' : 'Khách mời được thêm vào giải',
            'message' => $message,
            'type' => 'tournament_guest_added',
            'is_pending_confirmation' => $isPending,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable) + [
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
