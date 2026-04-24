<?php

namespace App\Events\SuperAdmin;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action = 'created';
    public string $eventType = 'report';

    public function __construct(
        public int $reportId,
        public string $reportType,
        public string $reason,
        public ?int $clubId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.report';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'report_id' => $this->reportId,
                'report_type' => $this->reportType,
                'reason' => $this->reason,
                'club_id' => $this->clubId,
            ],
        ];
    }
}
