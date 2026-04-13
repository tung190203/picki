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
                    'user' => $this->when($user !== null, function () use ($user, $isGuest, $p) {
                        // Format scores như key-value object (đúng format)
                        $sportsArray = [];
                        if ($user->relationLoaded('sports')) {
                            foreach ($user->sports as $sport) {
                                $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                                $types = ['personal_score', 'dupr_score', 'vndupr_score'];
                                $formattedScores = [];
                                foreach ($types as $type) {
                                    $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
                                    $scoreValue = $latestScore ? $latestScore->score_value : 0;
                                    $formattedScores[$type] = number_format($scoreValue, 3);
                                }
                                // Guest override vndupr_score
                                if ($isGuest) {
                                    $formattedScores['vndupr_score'] = number_format((float) ($p?->estimated_level ?? 0), 3);
                                }
                                $sportsArray[] = [
                                    'sport_id' => $sport->sport_id,
                                    'scores' => $formattedScores,
                                ];
                            }
                        }

                        return [
                            'id' => $user->id,
                            'full_name' => $user->full_name,
                            'avatar_url' => $user->avatar_url,
                            'visibility' => $user->visibility,
                            'sports' => $sportsArray,
                        ];
                    }),
                ];
            }),
        ];
    }
}
