<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiniTournamentMemberAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $miniTournamentId;
    public string $miniTournamentName;
    public array $memberData;
    public string $memberType;
    public string $action = 'member_added';
    public string $eventType = 'mini_tournament';

    public function __construct(int $miniTournamentId, string $miniTournamentName, array $memberData, string $memberType = 'participant')
    {
        $this->miniTournamentId = $miniTournamentId;
        $this->miniTournamentName = $miniTournamentName;
        $this->memberData = $memberData;
        $this->memberType = $memberType;
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
                'mini_tournament_id' => $this->miniTournamentId,
                'mini_tournament_name' => $this->miniTournamentName,
                'member' => $this->memberData,
                'member_type' => $this->memberType,
            ],
        ];
    }
}
