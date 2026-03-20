<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GuestAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected MiniTournament $tournament,
        protected MiniParticipant $guestParticipant,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        $guestName = $this->guestParticipant->guest_name;
        $tournamentName = $this->tournament->name;
        $feeText = $this->tournament->has_fee
            ? ' và cần thanh toán phí tham gia kèo đấu'
            : '';

        return [
            'mini_tournament_id' => $this->tournament->id,
            'participant_id' => $this->guestParticipant->id,
            'guarantor_participant_id' => $this->guestParticipant->guarantor_participant_id ?? null,
            'title' => 'Bạn đang bảo lãnh một khách mời',
            'message' => "Bạn đang bảo lãnh khách mời \"{$guestName}\" trong kèo \"{$tournamentName}\"{$feeText}. Vui lòng hoàn tất thanh toán giúp khách.",
            'type' => 'guest_added',
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
