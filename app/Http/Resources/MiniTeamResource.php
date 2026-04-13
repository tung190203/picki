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

                $user = $member->relationLoaded('user') ? $member->user : null;

                return [
                    'id' => $member->user_id,
                    'team_id' => $this->id,
                    'full_name' => $isGuest
                        ? ($p->guest_name ?? $user?->full_name)
                        : ($user?->full_name ?? ''),
                    'avatar_url' => $isGuest
                        ? ($p->guest_avatar ?? $user?->avatar_url)
                        : ($user?->avatar_url ?? ''),
                    'is_guest' => $isGuest,
                    'visibility' => $user?->visibility,
                    'user' => $this->when($user !== null, function () use ($user) {
                        return [
                            'id' => $user->id,
                            'full_name' => $user->full_name,
                            'avatar_url' => $user->avatar_url,
                            'visibility' => $user->visibility,
                            'sports' => $user->relationLoaded('sports')
                                ? $user->sports->map(fn ($s) => [
                                    'sport_id' => $s->sport_id,
                                    'scores' => $s->relationLoaded('scores')
                                        ? $s->scores->map(fn ($sc) => [
                                            'score_type' => $sc->score_type,
                                            'score_value' => $sc->score_value,
                                        ])->toArray()
                                        : $s->scores()->get()->map(fn ($sc) => [
                                            'score_type' => $sc->score_type,
                                            'score_value' => $sc->score_value,
                                        ])->toArray(),
                                ])->toArray()
                                : [],
                        ];
                    }),
                ];
            }),
        ];
    }
}
