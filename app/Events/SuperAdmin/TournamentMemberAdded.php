<?php

namespace App\Events\SuperAdmin;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentMemberAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tournamentId;
    public string $tournamentName;
    public array $memberData;
    public string $memberType;
    public string $action = 'member_added';
    public string $eventType = 'tournament';

    public function __construct(int $tournamentId, string $tournamentName, array $memberData, string $memberType = 'participant')
    {
        $this->tournamentId = $tournamentId;
        $this->tournamentName = $tournamentName;
        $this->memberData = $memberData;
        $this->memberType = $memberType;
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
                'tournament_id' => $this->tournamentId,
                'tournament_name' => $this->tournamentName,
                'member' => $this->memberData,
                'member_type' => $this->memberType,
            ],
        ];
    }
}
