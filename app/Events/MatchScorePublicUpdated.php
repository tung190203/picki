<?php

namespace App\Events;

use App\Models\Matches;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Collection;

class MatchScorePublicUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Matches $match, public ?Collection $results = null)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('match.' . $this->match->id)];
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
        $results = $this->results ?? $this->match->results ?? collect();

        $startedAt = $this->match->started_at;
        if (is_string($startedAt)) {
            $startedAt = $startedAt ? \Carbon\Carbon::parse($startedAt)->toIso8601String() : null;
        } else {
            $startedAt = $startedAt?->toIso8601String();
        }

        return [
            'match_id' => $this->match->id,
            'live_status' => $this->match->live_status,
            'started_at' => $startedAt,
            'current_set' => $this->match->current_set,
            'serving_team_id' => $this->match->serving_team_id,
            'team1_timeout_used' => $this->match->team1_timeout_used,
            'team2_timeout_used' => $this->match->team2_timeout_used,
            'elapsed_seconds' => $startedAt ? max(0, now()->diffInSeconds(\Carbon\Carbon::parse($startedAt))) : null,
            'version' => $this->match->match_version,
            'updated_at' => ($this->match->updated_at instanceof \Carbon\Carbon)
                ? $this->match->updated_at->toIso8601String()
                : ($this->match->updated_at ? \Carbon\Carbon::parse($this->match->updated_at)->toIso8601String() : null),
            'home_team_confirm' => $this->match->home_team_confirm,
            'away_team_confirm' => $this->match->away_team_confirm,
            'status' => $this->match->status,
            'sets' => $results
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
