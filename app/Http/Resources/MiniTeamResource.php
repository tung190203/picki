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
        $members = $this->resource?->members ?? collect();
        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => $members->map(function ($member) {
                /** @var \App\Models\MiniParticipant|null $p */
                $p = $member->miniTournamentParticipant();
                // Ưu tiên cờ is_guest từ chính MiniTeamMember (đã có sẵn khi tạo)
                // để tránh phụ thuộc lookup MiniParticipant (có thể thiếu do data cũ).
                $isGuest = (bool) $member->is_guest || (bool) ($p?->is_guest);
                $guestName = $member->guest_name ?: ($p?->guest_name ?? null);
                $guestAvatar = $member->guest_avatar ?: ($p?->guest_avatar ?? null);

                $user = $member->relationLoaded('user') ? $member->user : null;

                return [
                    'id' => $p?->id ?? $member->user_id, // MiniParticipant.id (key ổn định cho FE), fallback user_id
                    'team_id' => $this->id,
                    'full_name' => $isGuest
                        ? ($guestName ?? $user?->full_name ?? 'Thành viên ảo')
                        : ($user?->full_name ?? ''),
                    'avatar_url' => $isGuest
                        ? ($guestAvatar ?? $user?->avatar_url)
                        : ($user?->avatar_url ?? ''),
                    'is_guest' => $isGuest,
                    'visibility' => $user?->visibility,
                    'user' => $this->when($user !== null, function () use ($user, $isGuest, $p) {
                        // Format scores như key-value object
                        $sportsArray = [];
                        if ($user->relationLoaded('sports')) {
                            foreach ($user->sports as $sport) {
                                $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                                $types = ['personal_score', 'dupr_score', 'vndupr_score', 'trinh_score'];
                                $formattedScores = [];
                                foreach ($types as $type) {
                                    $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
                                    $scoreValue = $latestScore ? $latestScore->score_value : 0;
                                    $formattedScores[$type] = number_format((float) $scoreValue, 3);
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
