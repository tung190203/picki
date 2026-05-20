<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListClubResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo_url' => $this->logo_url,
            'is_verified' => (bool) $this->is_verified,
            'created_by' => $this->created_by,
            'quantity_members' => $this->whenLoaded('members', fn() => $this->members->count(), 0),
            'is_joined' => auth()->check()
                ? ($this->relationLoaded('activeMembers')
                    ? $this->activeMembers->contains('user_id', auth()->id())
                    : false)
                : false,
            'skill_level' => $this->whenLoaded('members', function () {
                $scores = $this->members
                    ->map(fn($member) => $this->getMemberVnduprScore($member))
                    ->filter(fn($score) => $score !== null);

                if ($scores->isEmpty()) {
                    return null;
                }

                return [
                    'min' => round($scores->min(), 3),
                    'max' => round($scores->max(), 3),
                ];
            }, null),
            'unread_notification_count' => (int) ($this->unread_notification_count ?? 0),
        ];
    }

    /**
     * Lấy điểm vndupr của member (từ user.vnduprScores hoặc user.sports.scores)
     */
    protected function getMemberVnduprScore($member): ?float
    {
        $user = $member->user ?? null;
        if (!$user) {
            return null;
        }
        $score = null;
        if ($user->relationLoaded('vnduprScores')) {
            $max = $user->vnduprScores->max('score_value');
            $score = $max !== null ? (float) $max : null;
        }
        if ($score === null && $user->relationLoaded('sports')) {
            foreach ($user->sports ?? [] as $userSport) {
                $scores = $userSport->relationLoaded('scores') ? $userSport->scores : collect();
                $vndupr = $scores->where('score_type', 'vndupr_score')->sortByDesc('created_at')->first();
                if ($vndupr) {
                    $score = (float) $vndupr->score_value;
                    break;
                }
            }
        }
        return $score;
    }
}
