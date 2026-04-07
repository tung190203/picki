<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniTeamResource extends JsonResource
{
    private ?int $miniTournamentId = null;

    public function forMiniTournament(?int $miniTournamentId): static
    {
        $this->miniTournamentId = $miniTournamentId;
        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => $this->members->map(function ($member) {
                /** @var \App\Models\MiniParticipant|null $p */
                $p = $member->relationLoaded('miniTournamentParticipant')
                    ? $member->miniTournamentParticipant
                    : null;
                $isGuest = (bool) ($p?->is_guest);

                return [
                    'id' => $member->user_id,
                    'full_name' => $isGuest ? ($p->guest_name ?? $member->user?->full_name) : ($member->user?->full_name ?? ''),
                    'avatar_url' => $isGuest ? ($p->guest_avatar ?? $member->user?->avatar_url) : ($member->user?->avatar_url ?? ''),
                    'is_guest' => $isGuest,
                ];
            }),
        ];
    }
}
