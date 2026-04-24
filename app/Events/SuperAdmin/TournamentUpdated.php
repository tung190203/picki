<?php

namespace App\Events\SuperAdmin;

use App\Models\Tournament;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Tournament $tournament;
    public array $previousData;
    public string $action = 'updated';
    public string $eventType = 'tournament';

    public function __construct(Tournament $tournament, array $previousData = [])
    {
        $this->tournament = $tournament;
        $this->previousData = $previousData;
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
        $changedFields = [];
        if (!empty($this->previousData)) {
            foreach ($this->tournament->getChanges() as $key => $value) {
                if (!in_array($key, ['updated_at'])) {
                    $changedFields[] = $key;
                }
            }
        }

        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'id' => $this->tournament->id,
                'name' => $this->tournament->name,
                'description' => $this->tournament->description,
                'status' => $this->tournament->status,
                'status_text' => $this->tournament->status_text,
                'is_featured' => $this->tournament->is_featured,
                'sport' => $this->tournament->sport ? [
                    'id' => $this->tournament->sport->id,
                    'name' => $this->tournament->sport->name,
                ] : null,
                'club' => $this->tournament->club ? [
                    'id' => $this->tournament->club->id,
                    'name' => $this->tournament->club->name,
                ] : null,
                'created_by' => $this->tournament->createdBy ? [
                    'id' => $this->tournament->createdBy->id,
                    'full_name' => $this->tournament->createdBy->full_name,
                    'avatar_url' => $this->tournament->createdBy->avatar_url,
                ] : null,
                'start_date' => $this->tournament->start_date,
                'end_date' => $this->tournament->end_date,
                'participants_count' => $this->tournament->participants->count() ?? 0,
                'poster_url' => $this->tournament->poster_url,
                'changed_fields' => $changedFields,
                'previous' => $this->previousData,
                'updated_at' => $this->tournament->updated_at?->toIso8601String(),
            ],
        ];
    }
}
