<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeOpened implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action = 'opened';
    public string $eventType = 'dispute';

    public function __construct(
        public int $disputeId,
        public int $matchId,
        public int $miniTournamentId,
        public string $reason,
        public ?string $status = 'open',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.dispute';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'dispute_id' => $this->disputeId,
                'match_id' => $this->matchId,
                'mini_tournament_id' => $this->miniTournamentId,
                'reason' => $this->reason,
                'status' => $this->status,
            ],
        ];
    }
}
