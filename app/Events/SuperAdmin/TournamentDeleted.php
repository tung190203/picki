<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tournamentId;
    public string $tournamentName;
    public string $action = 'deleted';
    public string $eventType = 'tournament';

    public function __construct(int $tournamentId, string $tournamentName)
    {
        $this->tournamentId = $tournamentId;
        $this->tournamentName = $tournamentName;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.tournament';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'id' => $this->tournamentId,
                'name' => $this->tournamentName,
                'deleted_at' => now()->toIso8601String(),
            ],
        ];
    }
}
