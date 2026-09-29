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
        // Build members list: real users + guest participants (merge tại response layer)
        $members = collect();

        foreach (($this->resource?->members ?? collect()) as $member) {
            $members->push([
                '__source' => 'user',
                'model' => $member,
                'participant' => $member->relationLoaded('tournamentParticipant')
                    ? $member->tournamentParticipant
                    : null,
            ]);
        }
        foreach (($this->resource?->guestMembers ?? collect()) as $tm) {
            if (!$tm->participant) continue;
            $members->push([
                '__source' => 'guest',
                'model' => $tm->participant,
                'participant' => $tm->participant,
            ]);
        }

        $scores = [];
        $resources = [];
        foreach ($members as $entry) {
            $participant = $entry['participant'];
            $model = $entry['model'];

            if ($participant?->is_guest) {
                $score = (float) ($participant->estimated_level ?? 0);
                if ($score > 0) {
                    $scores[] = $score;
                }
                $resources[] = new TeamMemberResource($participant);
            } else {
                $memberSports = $model->relationLoaded('sports') ? $model->sports : collect();
                foreach ($memberSports as $sport) {
                    $sportScores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                    $latest = $sportScores->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')->first();
                    if ($latest) {
                        $score = (float) $latest->score_value;
                        if ($score > 0) {
                            $scores[] = $score;
                        }
                        break;
                    }
                }
                $resources[] = new TeamMemberResource($model);
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
            'members' => collect($resources)->map(fn($r) => $r->toArray($request))->values(),
            'total_vndupr' => $totalVndupr,
        ];
    }
}
