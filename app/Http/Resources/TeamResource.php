<?php

namespace App\Http\Resources;

use App\Support\TournamentTeamMemberHydrator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    private ?int $tournamentId = null;

    public function forTournament(?int $tournamentId): static
    {
        $clone = clone $this;
        $clone->tournamentId = $tournamentId;
        return $clone;
    }

    public function toArray(Request $request): array
    {
        if ($this->tournamentId !== null) {
            // Hydrate members with participant info for UserMatchStatsController
            TournamentTeamMemberHydrator::hydrateTeam($this->resource, $this->tournamentId);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'tournament_type_id' => $this->tournament_type_id,
            'avatar' => $this->avatar,
            'members' => TeamMemberResource::collection($this->members),
        ];
    }
}
