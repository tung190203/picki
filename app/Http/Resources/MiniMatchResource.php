<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniMatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $groupedResults = [];
        if ($this->relationLoaded('results')) {
            $groupedResults = $this->results
                ->groupBy('set_number')
                ->mapWithKeys(function ($set, $setNumber) {
                    return [
                        "set{$setNumber}" => MiniMatchResultResource::collection($set)
                    ];
                })->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mini_tournament_id' => $this->mini_tournament_id,
            'team1' => new MiniTeamResource($this->whenLoaded('team1')),
            'team2' => new MiniTeamResource($this->whenLoaded('team2')),
            'status' => $this->status,
            'team_win_id' => $this->team_win_id,
            'results_by_sets' => $groupedResults,
            'competition_location' => $this->whenLoaded('miniTournament', function () {
                return optional(optional($this->miniTournament)->competitionLocation)?->only(['id', 'name', 'latitude', 'longitude']);
            }),
            'has_anchor' => collect()
            ->merge(
                $this->whenLoaded('team1', fn () =>
                    $this->team1->members->pluck('user')
                ) ?? collect()
            )
            ->merge(
                $this->whenLoaded('team2', fn () =>
                    $this->team2->members->pluck('user')
                ) ?? collect()
            )
            ->filter()
            ->contains(function ($user) {
                return $user->is_anchor
                    || ($user->total_matches_has_anchor ?? 0) >= 10;
            }),
        ];
    }
}
