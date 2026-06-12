<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniMatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    private function buildTeam($team, ?int $byeUserId): ?array
    {
        if (!($team instanceof MissingValue) && $team !== null && $team->id !== null) {
            return (new MiniTeamResource($team))
                ->forMiniTournament($this->mini_tournament_id)
                ->setByeParticipants($byeUserId !== null ? [$byeUserId] : [])
                ->toArray(request());
        }

        if ($this->is_bye && $byeUserId !== null) {
            return [
                'id' => null,
                'name' => null,
                'members' => [
                    [
                        'id' => $byeUserId,
                        'team_id' => null,
                        'full_name' => $this->byeParticipant?->user?->full_name
                            ?? $this->byeParticipant?->guest_name
                            ?? '',
                        'avatar_url' => $this->byeParticipant?->user?->avatar_url
                            ?? $this->byeParticipant?->guest_avatar
                            ?? '',
                        'is_guest' => (bool) ($this->byeParticipant?->is_guest),
                        'is_bye' => true,
                        'visibility' => $this->byeParticipant?->user?->visibility,
                    ],
                ],
            ];
        }

        return null;
    }

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

        $byeUserId = $this->byeParticipant?->user_id;
        $team1Data = $this->buildTeam($this->whenLoaded('team1'), $byeUserId);
        $team2Data = $this->buildTeam($this->whenLoaded('team2'), $byeUserId);

        $hasAnchor = false;
        if ($this->relationLoaded('team1') && $this->team1 !== null && !($this->team1 instanceof MissingValue)) {
            foreach ($this->team1->members->pluck('user') as $user) {
                if ($user && ($user->is_anchor || ($user->total_matches_has_anchor ?? 0) >= 10)) {
                    $hasAnchor = true;
                    break;
                }
            }
        }
        if (!$hasAnchor && $this->relationLoaded('team2') && $this->team2 !== null && !($this->team2 instanceof MissingValue)) {
            foreach ($this->team2->members->pluck('user') as $user) {
                if ($user && ($user->is_anchor || ($user->total_matches_has_anchor ?? 0) >= 10)) {
                    $hasAnchor = true;
                    break;
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mini_tournament_id' => $this->mini_tournament_id,
            'round_number' => $this->round_number,
            'club_id' => $this->miniTournament?->club_id,
            'club' => ($this->relationLoaded('miniTournament') && $this->miniTournament?->relationLoaded('club') && $this->miniTournament?->club && $this->miniTournament?->club->is_public !== false)
                ? new \App\Http\Resources\ClubResource($this->miniTournament->club)
                : null,
            'team1' => $team1Data,
            'team2' => $team2Data,
            'status' => $this->status,
            'team_win_id' => $this->team_win_id,
            'results_by_sets' => $groupedResults,
            'competition_location' => $this->whenLoaded('miniTournament', function () {
                return optional(optional($this->miniTournament)->competitionLocation)?->only(['id', 'name', 'latitude', 'longitude']);
            }),
            'has_anchor' => $hasAnchor,
            'is_bye' => (bool) $this->is_bye,
        ];
    }
}
