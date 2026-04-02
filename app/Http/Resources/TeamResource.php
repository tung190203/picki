<?php

namespace App\Http\Resources;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    private ?int $tournamentId = null;

    public function forTournament(?int $tournamentId): static
    {
        $this->tournamentId = $tournamentId;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $participantMap = collect();
        if ($this->tournamentId) {
            $memberIds = $this->members->pluck('id');
            $participantMap = Participant::where('tournament_id', $this->tournamentId)
                ->whereIn('user_id', $memberIds)
                ->get()
                ->keyBy('user_id');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => $this->members->map(function ($user) use ($participantMap) {
                /** @var \App\Models\Participant|null $p */
                $p = $participantMap->get($user->id);

                return [
                    'id' => $user->id,
                    'tournament_participant' => [
                        'is_guest' => (bool) $p?->is_guest,
                        'user' => [
                            'full_name' => $p?->is_guest ? ($p->guest_name ?? $user->full_name) : $user->full_name,
                            'avatar_url' => $p?->is_guest ? ($p->guest_avatar ?? $user->avatar_url) : $user->avatar_url,
                        ],
                    ],
                ];
            }),
        ];
    }
}
