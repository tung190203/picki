<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamLeaderboardResource extends JsonResource
{
    private int $rank;
    private int $totalMatches;
    private float $winRate;
    private int|null $lastRound;

    public function __construct(array $data, int $rank, int $totalMatches, float $winRate, int|null $lastRound = null)
    {
        parent::__construct($data);
        $this->rank = $rank;
        $this->totalMatches = $totalMatches;
        $this->winRate = $winRate;
        $this->lastRound = $lastRound;
    }

    public function toArray(Request $request): array
    {
        $team = $this->resource;

        return [
            'id'               => $team['id'],
            'name'             => $team['name'],
            'avatar_url'       => $team['avatar'],
            'vndupr_avg'       => $team['vndupr_avg'],
            'members'          => $team['members'],
            'tournament_types' => $team['tournament_types'] ?? [],
            'is_my_team'       => $team['is_my_team'] ?? false,
            'rank'             => $this->rank,
            'total_matches'    => $this->totalMatches,
            'win_rate'         => round($this->winRate, 2),
            'last_round'       => $this->lastRound,
        ];
    }

    public function __get($key)
    {
        if (in_array($key, ['rank', 'totalMatches', 'winRate', 'lastRound'])) {
            return $this->$key;
        }
        return $this->resource[$key] ?? null;
    }
}
