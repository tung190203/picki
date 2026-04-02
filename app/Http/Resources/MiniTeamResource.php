<?php

namespace App\Http\Resources;

use App\Models\MiniParticipant;
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
        $participantMap = collect();
        if ($this->miniTournamentId) {
            $memberIds = $this->members->pluck('id');
            $participantMap = MiniParticipant::where('mini_tournament_id', $this->miniTournamentId)
                ->whereIn('user_id', $memberIds)
                ->get()
                ->keyBy('user_id');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => $this->members->map(function ($member) use ($participantMap) {
                /** @var \App\Models\MiniParticipant|null $p */
                $p = $participantMap->get($member->id);
                $isGuest = $p?->is_guest;

                return [
                    'id' => $member->id,
                    'full_name' => $isGuest ? ($p->guest_name ?? $member->full_name) : $member->full_name,
                    'avatar_url' => $isGuest ? ($p->guest_avatar ?? $member->avatar_url) : $member->avatar_url,
                    'is_guest' => $isGuest,
                ];
            }),
        ];
    }
}
