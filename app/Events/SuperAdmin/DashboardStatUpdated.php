<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardStatUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventType = 'dashboard_stat';

    // stat_key: 'tournaments_this_month' | 'user_growth_week' | 'mini_tournament_growth'
    public function __construct(
        public string $statKey,
        public mixed $value,
        public string $action = 'incremented',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.dashboard_stat';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'stat_key' => $this->statKey,
                'value' => $this->value,
            ],
        ];
    }
}
