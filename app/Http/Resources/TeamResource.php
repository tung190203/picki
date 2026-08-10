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
        $members = $this->resource?->members ?? collect();
        $scores = [];
        foreach ($members as $member) {
            $participant = $member->relationLoaded('tournamentParticipant')
                ? $member->tournamentParticipant
                : null;

            if ($participant?->is_guest) {
                // Guest: use estimated_level from participant record
                $score = (float) ($participant->estimated_level ?? 0);
                if ($score > 0) {
                    $scores[] = $score;
                }
            } else {
                // Real user: use vndupr_score from UserSportScore
                $memberSports = $member->relationLoaded('sports') ? $member->sports : collect();
                foreach ($memberSports as $sport) {
                    $sportScores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                    $latest = $sportScores->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')->first();
                    if ($latest) {
                        $score = (float) $latest->score_value;
                        if ($score > 0) {
                            $scores[] = $score;
                        }
                        break; // one sport per member
                    }
                }
            }
        }

        $totalVndupr = count($scores) >= 1
            ? round(array_sum($scores), 3)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tournament_id' => $this->tournament_id,
            'tournament_type_id' => $this->tournament_type_id,
            'avatar' => $this->avatar,
            'members' => TeamMemberResource::collection($members),
            'total_vndupr' => $totalVndupr,
        ];
    }
}
