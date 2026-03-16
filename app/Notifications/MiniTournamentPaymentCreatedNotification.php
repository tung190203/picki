<?php

namespace App\Notifications;

use App\Models\MiniTournament;
use App\Models\MiniParticipantPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MiniTournamentPaymentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $tournament;
    private $payment;
    private $feePerPerson;

    public function __construct(MiniTournament $tournament, MiniParticipantPayment $payment, int $feePerPerson)
    {
        $this->tournament = $tournament;
        $this->payment = $payment;
        $this->feePerPerson = $feePerPerson;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        $isConfirmed = $this->payment->status === MiniParticipantPayment::STATUS_CONFIRMED;
        
        return [
            'type' => 'mini_tournament_payment_created',
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'payment_id' => $this->payment->id,
            'amount' => $this->feePerPerson,
            'status' => $this->payment->status,
            'message' => $isConfirmed 
                ? "Kèo \"{$this->tournament->name}\" đã kết thúc. Bạn là chủ kèo nên đã được mặc định thanh toán {$this->feePerPerson} VND"
                : "Kèo \"{$this->tournament->name}\" đã kết thúc. Vui lòng thanh toán {$this->feePerPerson} VND",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'type' => 'mini_tournament_payment_created',
            'tournament_id' => $this->tournament->id,
            'tournament_name' => $this->tournament->name,
            'payment_id' => $this->payment->id,
            'amount' => $this->feePerPerson,
            'status' => $this->payment->status,
        ];
    }
}
