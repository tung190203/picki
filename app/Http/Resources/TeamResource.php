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

        // Tính vndupr_avg cho team
        // Mỗi member đã được hydrate tournamentParticipant + sports.
        // Guest  → dùng estimated_level
        // User   → dùng vndupr_score (từ sports)
        $members = $this->resource->members ?? collect();
        $scores = [];
        foreach ($members as $member) {
            $participant = $member->relationLoaded('tournamentParticipant')
                ? $member->tournamentParticipant
                : null;
            if ($participant?->is_guest) {
                $scores[] = (float) ($participant->estimated_level ?? 0);
            } else {
                $memberSports = $member->relationLoaded('sports') ? $member->sports : collect();
                $vndupr = 0.0;
                foreach ($memberSports as $sport) {
                    $sportScores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                    $latest = $sportScores->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')->first();
                    if ($latest) {
                        $vndupr = (float) $latest->score_value;
                        break;
                    }
                }
                $scores[] = $vndupr;
            }
        }
        $vnduprAvg = count($scores) >= 1
            ? round(array_sum($scores) / count($scores), 3)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'tournament_type_id' => $this->tournament_type_id,
            'avatar' => $this->avatar,
            'members' => TeamMemberResource::collection($members),
            'vndupr_avg' => $vnduprAvg,
        ];
    }
}
