<?php

namespace App\Events;

use App\Models\Matches;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Collection;

class MatchScorePublicUpdated implements ShouldBroadcastNow
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

    /**
     * Gọn payload — chỉ fields FE thực sự dùng để reconcile.
     * Bỏ qua việc lazy-load relations nặng.
     */
    public function broadcastWith(): array
    {
        $results = $this->results ?? $this->match->getRelation('results') ?? collect();

        $sets = $results
            ->where('team_id', $this->match->home_team_id)
            ->sortBy('set_number')
            ->map(fn ($r) => [
                'set_number' => (int) $r->set_number,
                'team1_score' => (int) $r->team_score,
                'team2_score' => (int) $r->opponent_score,
                'serving_position' => (int) ($r->serving_position ?? 0),
            ])
            ->values()
            ->toArray();

        return [
            'match_id' => $this->match->id,
            'live_status' => $this->match->live_status,
            'current_set' => (int) $this->match->current_set,
            'serving_team_id' => $this->match->serving_team_id,
            'team1_timeout_used' => (int) $this->match->team1_timeout_used,
            'team2_timeout_used' => (int) $this->match->team2_timeout_used,
            'version' => $this->match->match_version,
            'sets' => $sets,
        ];
    }
}