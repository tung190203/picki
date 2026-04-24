<?php

namespace App\Events\SuperAdmin;

use App\Models\Matches;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentMatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Matches $match;
    public string $action = 'match_updated';
    public string $eventType = 'match';

    public function __construct(Matches $match)
    {
        $this->match = $match;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.match';
    }

    public function broadcastWith(): array
    {
        $tournamentType = $this->match->tournamentType;
        $tournament = $tournamentType?->tournament;

        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournament?->name,
                'match' => [
                    'id' => $this->match->id,
                    'round' => $this->match->round,
                    'status' => $this->match->status,
                    'scheduled_at' => $this->match->scheduled_at,
                    'court' => $this->match->court,
                    'winner_id' => $this->match->winner_id,
                    'home_team' => $this->match->homeTeam ? [
                        'id' => $this->match->homeTeam->id,
                        'name' => $this->match->homeTeam->name,
                    ] : null,
                    'away_team' => $this->match->awayTeam ? [
                        'id' => $this->match->awayTeam->id,
                        'name' => $this->match->awayTeam->name,
                    ] : null,
                    'results' => $this->match->results->map(fn($r) => [
                        'home_score' => $r->home_score,
                        'away_score' => $r->away_score,
                        'set_number' => $r->set_number,
                    ])->toArray(),
                ],
            ],
        ];
    }
}
