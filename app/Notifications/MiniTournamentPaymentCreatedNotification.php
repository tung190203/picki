<?php

namespace App\Notifications;

use App\Models\MiniTournament;
use App\Models\MiniParticipantPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentPaymentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $tournament;
    protected $payment;
    protected $feePerPerson;
    protected bool $isConfirmed;
    protected string $title;
    protected string $message;

    public function __construct(MiniTournament $tournament, MiniParticipantPayment $payment, int $feePerPerson)
    {
        $this->tournament = $tournament;
        $this->payment = $payment;
        $this->feePerPerson = $feePerPerson;
        $this->isConfirmed = $payment->status === MiniParticipantPayment::STATUS_CONFIRMED;
        $this->title = $this->isConfirmed ? 'Thanh toán kèo đấu' : 'Yêu cầu thanh toán kèo đấu';
        $this->message = $this->isConfirmed
            ? "Kèo \"{$tournament->name}\" đã bắt đầu. Bạn là chủ kèo nên đã được mặc định thanh toán {$feePerPerson} VND."
            : "Kèo \"{$tournament->name}\" đã bắt đầu. Bạn cần thanh toán {$feePerPerson} VND để hoàn tất.";
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'mini_tournament_payment_created',
            'mini_tournament_id' => $this->tournament->id,
            'payment_id' => $this->payment->id,
            'title' => $this->title,
            'message' => $this->message,
            'amount' => $this->feePerPerson,
            'status' => $this->payment->status,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'type' => 'mini_tournament_payment_created',
            'mini_tournament_id' => $this->tournament->id,
            'payment_id' => $this->payment->id,
            'title' => $this->title,
            'message' => $this->message,
            'amount' => $this->feePerPerson,
            'status' => $this->payment->status,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'mini_tournament_payment_created',
            'mini_tournament_id' => $this->tournament->id,
            'payment_id' => $this->payment->id,
            'title' => $this->title,
            'message' => $this->message,
            'amount' => $this->feePerPerson,
            'status' => $this->payment->status,
        ];
    }
}
