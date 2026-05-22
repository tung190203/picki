<?php

namespace App\Events;

use App\Models\QuickMatch;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickMatchConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public QuickMatch $quickMatch;
    public array $teamAMembersData;
    public array $teamBMembersData;

    public function __construct(QuickMatch $quickMatch, ?array $teamAMembersData = null, ?array $teamBMembersData = null)
    {
        $this->quickMatch = $quickMatch;
        $this->teamAMembersData = $teamAMembersData ?? $this->resolveTeamMembers($quickMatch->team_a ?? []);
        $this->teamBMembersData = $teamBMembersData ?? $this->resolveTeamMembers($quickMatch->team_b ?? []);
    }

    protected function resolveTeamMembers(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return User::whereIn('id', $ids)
            ->get([
                'id', 'full_name', 'avatar_url', 'gender', 'visibility',
                'is_anchor', 'total_matches_has_anchor',
            ])
            ->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'avatar_url' => $user->avatar_url,
                'gender' => $user->gender,
                'gender_text' => $user->gender_text,
                'visibility' => $user->visibility,
                'is_verify' => (bool) ($user->total_matches_has_anchor >= 10),
                'is_anchor' => (bool) $user->is_anchor,
                'is_guest' => false,
                'guest_name' => null,
                'guest_avatar' => null,
            ])
            ->toArray();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('quick-match.' . $this->quickMatch->id)];
    }

    public function broadcastAs(): string
    {
        return 'quick_match.confirmed';
    }

    public function broadcastWith(): array
    {
        $this->quickMatch->loadMissing(['creator', 'competitionLocation']);

        return [
            'quick_match' => [
                'id' => $this->quickMatch->id,
                'name' => $this->quickMatch->name,
                'avatar_url' => $this->quickMatch->avatar_url,
                'note' => $this->quickMatch->note,
                'match_type' => $this->quickMatch->match_type,
                'status' => $this->quickMatch->status,
                'created_by' => $this->quickMatch->created_by,
                'team_a' => [
                    'user_ids' => array_map('intval', $this->quickMatch->team_a ?? []),
                    'team_name' => $this->teamAName(),
                    'users' => $this->teamAMembersData,
                ],
                'team_b' => [
                    'user_ids' => array_map('intval', $this->quickMatch->team_b ?? []),
                    'team_name' => $this->teamBName(),
                    'users' => $this->teamBMembersData,
                ],
                'score' => [
                    'team_a' => array_map('intval', $this->quickMatch->score['team_a'] ?? []),
                    'team_b' => array_map('intval', $this->quickMatch->score['team_b'] ?? []),
                ],
                'winner' => $this->quickMatch->winner,
                'qr_code_url' => $this->quickMatch->qr_code
                    ? url("/api/quick-matches/confirm/{$this->quickMatch->qr_code}")
                    : null,
                'creator' => $this->quickMatch->creator ? [
                    'id' => $this->quickMatch->creator->id,
                    'name' => $this->quickMatch->creator->full_name,
                    'avatar_url' => $this->quickMatch->creator->avatar_url,
                    'gender' => $this->quickMatch->creator->gender,
                ] : null,
                'is_super_admin_created' => (bool) ($this->quickMatch->creator->is_super_admin ?? false),
                'is_referee_scoring' => (bool) ($this->quickMatch->is_referee_scoring ?? false),
                'scheduled_at' => $this->quickMatch->scheduled_at?->toIso8601String(),
                'competition_location' => $this->quickMatch->competitionLocation ? [
                    'id' => $this->quickMatch->competitionLocation->id,
                    'name' => $this->quickMatch->competitionLocation->name,
                    'address' => $this->quickMatch->competitionLocation->address,
                    'latitude' => $this->quickMatch->competitionLocation->latitude,
                    'longitude' => $this->quickMatch->competitionLocation->longitude,
                ] : null,
                'created_at' => $this->quickMatch->created_at?->toIso8601String(),
                'updated_at' => $this->quickMatch->updated_at?->toIso8601String(),
            ],
        ];
    }

    protected function teamAName(): ?string
    {
        $names = array_column($this->teamAMembersData, 'full_name');
        $result = implode(' & ', $names);
        return $result ?: null;
    }

    protected function teamBName(): ?string
    {
        $names = array_column($this->teamBMembersData, 'full_name');
        $result = implode(' & ', $names);
        return $result ?: null;
    }
}
