<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiniTournamentDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $miniTournamentId;
    public string $miniTournamentName;
    public string $action = 'deleted';
    public string $eventType = 'mini_tournament';

    public function __construct(int $miniTournamentId, string $miniTournamentName)
    {
        $this->miniTournamentId = $miniTournamentId;
        $this->miniTournamentName = $miniTournamentName;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.mini_tournament';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'id' => $this->miniTournamentId,
                'name' => $this->miniTournamentName,
                'deleted_at' => now()->toIso8601String(),
            ],
        ];
    }
}
