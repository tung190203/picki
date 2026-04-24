<?php

namespace App\Events\SuperAdmin;

use App\Models\MiniMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MiniMatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MiniMatch $miniMatch;
    public string $action = 'match_updated';
    public string $eventType = 'mini_match';

    public function __construct(MiniMatch $miniMatch)
    {
        $this->miniMatch = $miniMatch;
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
        $miniTournament = $this->miniMatch->miniTournament;

        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'mini_tournament_id' => $miniTournament?->id,
                'mini_tournament_name' => $miniTournament?->name,
                'match' => [
                    'id' => $this->miniMatch->id,
                    'name' => $this->miniMatch->name,
                    'status' => $this->miniMatch->status,
                    'participant_win_id' => $this->miniMatch->participant_win_id,
                    'team_win_id' => $this->miniMatch->team_win_id,
                    'participant1' => $this->miniMatch->participant1 ? [
                        'id' => $this->miniMatch->participant1->id,
                        'user' => $this->miniMatch->participant1->user ? [
                            'id' => $this->miniMatch->participant1->user->id,
                            'full_name' => $this->miniMatch->participant1->user->full_name,
                            'avatar_url' => $this->miniMatch->participant1->user->avatar_url,
                        ] : null,
                    ] : null,
                    'participant2' => $this->miniMatch->participant2 ? [
                        'id' => $this->miniMatch->participant2->id,
                        'user' => $this->miniMatch->participant2->user ? [
                            'id' => $this->miniMatch->participant2->user->id,
                            'full_name' => $this->miniMatch->participant2->user->full_name,
                            'avatar_url' => $this->miniMatch->participant2->user->avatar_url,
                        ] : null,
                    ] : null,
                    'team1' => $this->miniMatch->team1 ? [
                        'id' => $this->miniMatch->team1->id,
                        'name' => $this->miniMatch->team1->name,
                    ] : null,
                    'team2' => $this->miniMatch->team2 ? [
                        'id' => $this->miniMatch->team2->id,
                        'name' => $this->miniMatch->team2->name,
                    ] : null,
                    'team_win' => $this->miniMatch->teamWin ? [
                        'id' => $this->miniMatch->teamWin->id,
                        'name' => $this->miniMatch->teamWin->name,
                    ] : null,
                    'results' => $this->miniMatch->results->map(fn($r) => [
                        'team_id' => $r->team_id,
                        'score' => $r->score,
                        'set_number' => $r->set_number,
                    ])->toArray(),
                ],
            ],
        ];
    }
}
