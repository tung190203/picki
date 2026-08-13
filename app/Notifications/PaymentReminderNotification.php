<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $tournament;
    protected $participant;

    public function __construct(MiniTournament $tournament, MiniParticipant $participant)
    {
        $this->tournament = $tournament;
        $this->participant = $participant;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'mini_tournament_id' => $this->tournament->id,
            'participant_id' => $this->participant->id,
            'title' => 'Nhắc nhở thanh toán phí tham gia',
            'message' => "Bạn chưa thanh toán phí tham gia kèo '{$this->tournament->name}'. Vui lòng thanh toán sớm nhất có thể.",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'mini_tournament_id' => $this->tournament->id,
            'participant_id' => $this->participant->id,
            'title' => 'Nhắc nhở thanh toán phí tham gia',
            'message' => "Bạn chưa thanh toán phí tham gia kèo '{$this->tournament->name}'. Vui lòng thanh toán sớm nhất có thể.",
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => 'Nhắc nhở thanh toán phí tham gia',
            'body' => "Bạn chưa thanh toán phí tham gia kèo '{$this->tournament->name}'. Vui lòng thanh toán sớm nhất có thể.",
            'data' => [
                'type' => 'PAYMENT_REMINDER',
                'mini_tournament_id' => (string) $this->tournament->id,
                'participant_id' => (string) $this->participant->id,
                'action' => 'open_payment',
            ],
        ];
    }
}
