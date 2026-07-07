<?php

namespace App\Events;

use App\Models\Matches;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchScoreUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Matches $match)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.' . $this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'match.score_updated';
    }

    public function shouldBroadcastAfterCommit(): bool
    {
        return true;
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'live_status' => $this->match->live_status,
            'started_at' => $this->match->started_at?->toIso8601String(),
            'current_set' => $this->match->current_set,
            'serving_team_id' => $this->match->serving_team_id,
            'team1_timeout_used' => $this->match->team1_timeout_used,
            'team2_timeout_used' => $this->match->team2_timeout_used,
            'version' => $this->match->match_version,
            'updated_at' => $this->match->updated_at?->toIso8601String(),
            'sets' => $this->match->results
                ->where('team_id', $this->match->home_team_id)
                ->sortBy('set_number')
                ->map(fn ($r) => [
                    'set_number' => $r->set_number,
                    'team1_score' => $r->team_score,
                    'team2_score' => $r->opponent_score,
                    'serving_position' => $r->serving_position,
                ])
                ->values()
                ->toArray(),
        ];
    }
}
