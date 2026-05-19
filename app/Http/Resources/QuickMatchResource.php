<?php

namespace App\Http\Resources;

use App\Http\Resources\CompetitionLocationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $score = $this->score ?? [];
        $teamAScore = $score['team_a'] ?? [];
        $teamBScore = $score['team_b'] ?? [];

        $teamANames = $this->teamAMembers()->pluck('full_name')->toArray();
        $teamBNames = $this->teamBMembers()->pluck('full_name')->toArray();
        $teamAName = implode(' & ', $teamANames);
        $teamBName = implode(' & ', $teamBNames);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'note' => $this->note,
            'match_type' => $this->match_type,
            'status' => $this->status,
            'created_by' => $this->created_by,

            'team_a' => [
                'user_ids' => array_map('intval', $this->team_a ?? []),
                'team_name' => $teamAName ?: null,
                'users' => UserListResource::collection($this->teamAMembers()),
            ],
            'team_b' => [
                'user_ids' => array_map('intval', $this->team_b ?? []),
                'team_name' => $teamBName ?: null,
                'users' => UserListResource::collection($this->teamBMembers()),
            ],

            'score' => [
                'team_a' => array_map('intval', $teamAScore),
                'team_b' => array_map('intval', $teamBScore),
            ],

            'winner' => $this->winner,

            'qr_code_url' => $this->qr_code
                ? url("/api/quick-matches/confirm/{$this->qr_code}")
                : null,

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->full_name,
                    'avatar_url' => $this->creator->avatar_url,
                    'gender' => $this->creator->gender,
                ];
            }),
            'is_super_admin_created' => $this->whenLoaded('creator')
                ? (bool) ($this->creator->is_super_admin ?? false)
                : false,

            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'competition_location' => new CompetitionLocationResource(
                $this->whenLoaded('competitionLocation')
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
