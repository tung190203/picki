<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action = 'confirmed';
    public string $eventType = 'payment';

    public function __construct(
        public int $miniTournamentId,
        public int $paymentId,
        public float $amount,
        public int $userId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.payment';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'mini_tournament_id' => $this->miniTournamentId,
                'payment_id' => $this->paymentId,
                'amount' => $this->amount,
                'user_id' => $this->userId,
            ],
        ];
    }
}
