<?php

namespace App\Events\SuperAdmin;

use App\Models\MiniTournament;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiniTournamentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MiniTournament $miniTournament;
    public string $action = 'created';
    public string $eventType = 'mini_tournament';

    public function __construct(MiniTournament $miniTournament)
    {
        $this->miniTournament = $miniTournament;
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
                'id' => $this->miniTournament->id,
                'name' => $this->miniTournament->name,
                'description' => $this->miniTournament->description,
                'status' => $this->miniTournament->status,
                'status_text' => $this->miniTournament->status_text,
                'sport' => $this->miniTournament->sport ? [
                    'id' => $this->miniTournament->sport->id,
                    'name' => $this->miniTournament->sport->name,
                ] : null,
                'club' => $this->miniTournament->club ? [
                    'id' => $this->miniTournament->club->id,
                    'name' => $this->miniTournament->club->name,
                ] : null,
                'creator' => $this->miniTournament->creator ? [
                    'id' => $this->miniTournament->creator->id,
                    'full_name' => $this->miniTournament->creator->full_name,
                    'avatar_url' => $this->miniTournament->creator->avatar_url,
                ] : null,
                'start_time' => $this->miniTournament->start_time,
                'end_time' => $this->miniTournament->end_time,
                'players_count' => $this->miniTournament->participants->count() ?? 0,
                'max_players' => $this->miniTournament->max_players,
                'competition_location' => $this->miniTournament->competitionLocation ? [
                    'id' => $this->miniTournament->competitionLocation->id,
                    'name' => $this->miniTournament->competitionLocation->name,
                    'address' => $this->miniTournament->competitionLocation->address,
                ] : null,
                'poster_url' => $this->miniTournament->poster ? asset('storage/' . $this->miniTournament->poster) : null,
                'created_at' => $this->miniTournament->created_at?->toIso8601String(),
            ],
        ];
    }
}
