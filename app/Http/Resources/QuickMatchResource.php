<?php

namespace App\Http\Resources;

use App\Http\Resources\CompetitionLocationResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickMatchResource extends JsonResource
{
    protected ?array $preResolvedTeamA = null;
    protected ?array $preResolvedTeamB = null;

    public function withResolvedTeam(?array $teamA, ?array $teamB): static
    {
        $clone = clone $this;
        $clone->preResolvedTeamA = $teamA;
        $clone->preResolvedTeamB = $teamB;
        return $clone;
    }

    public function toArray(Request $request): array
    {
        $score = $this->score ?? [];
        $teamAScore = $score['team_a'] ?? [];
        $teamBScore = $score['team_b'] ?? [];

        $teamANames = $this->preResolvedTeamA
            ? array_column($this->preResolvedTeamA, 'full_name')
            : $this->teamAMembers()->pluck('full_name')->toArray();
        $teamBNames = $this->preResolvedTeamB
            ? array_column($this->preResolvedTeamB, 'full_name')
            : $this->teamBMembers()->pluck('full_name')->toArray();
        $teamAName = implode(' & ', $teamANames);
        $teamBName = implode(' & ', $teamBNames);

        $teamAUsers = $this->preResolvedTeamA
            ?? User::whereIn('id', $this->team_a ?? [])->get([
                'id', 'full_name', 'avatar_url', 'gender', 'visibility',
                'is_anchor', 'total_matches_has_anchor',
            ])->map(fn ($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'visibility' => $u->visibility,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
                'gender' => $u->gender,
                'gender_text' => $u->gender_text,
                'play_times' => [],
                'sports' => [],
                'is_manager' => false,
                'rank_in_club' => null,
                'is_anchor' => (bool) $u->is_anchor,
                'is_verify' => (bool) ($u->total_matches_has_anchor >= 10),
                'is_guest' => false,
                'guest_name' => null,
                'guest_avatar' => null,
            ])->toArray();
        $teamBUsers = $this->preResolvedTeamB
            ?? User::whereIn('id', $this->team_b ?? [])->get([
                'id', 'full_name', 'avatar_url', 'gender', 'visibility',
                'is_anchor', 'total_matches_has_anchor',
            ])->map(fn ($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'visibility' => $u->visibility,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
                'gender' => $u->gender,
                'gender_text' => $u->gender_text,
                'play_times' => [],
                'sports' => [],
                'is_manager' => false,
                'rank_in_club' => null,
                'is_anchor' => (bool) $u->is_anchor,
                'is_verify' => (bool) ($u->total_matches_has_anchor >= 10),
                'is_guest' => false,
                'guest_name' => null,
                'guest_avatar' => null,
            ])->toArray();

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
                'users' => $teamAUsers,
            ],
            'team_b' => [
                'user_ids' => array_map('intval', $this->team_b ?? []),
                'team_name' => $teamBName ?: null,
                'users' => $teamBUsers,
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

            'is_referee_scoring' => (bool) ($this->is_referee_scoring ?? false),

            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'competition_location' => new CompetitionLocationResource(
                $this->whenLoaded('competitionLocation')
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
