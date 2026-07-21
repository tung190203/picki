<?php

namespace App\Http\Resources\Search;

use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserSportResource;

class SearchPlayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scores = $this->whenLoaded('sports',
            fn() => $this->sports->firstWhere('sport_id', 1)?->scores ?? collect(),
            fn() => collect()
        );

        $vnduprScore = $scores->firstWhere('score_type', 'vndupr_score');

        // Use preloaded batch stats if available (set by SearchV2Controller::paginate),
        // otherwise fall back to the per-row query for backward compatibility.
        if (isset($this->preloaded_sport_stats)) {
            $stats = $this->preloaded_sport_stats;
        } else {
            $stats = User::getSportStats($this->id, 1, false);
        }

        $isFollow = isset($this->is_following_count)
            ? (bool) $this->is_following_count
            : ($request->user() ? $request->user()->isFollowing($this->resource) : false);

        return [
            'id'          => $this->id,
            'full_name'   => $this->full_name,
            'avatar_url'  => $this->avatar_url,
            'gender'      => $this->gender,
            'gender_text' => $this->gender_text ?? null,
            'age_group'   => $this->age_group ?? null,
            'visibility'  => $this->visibility,
            'address'     => $this->address ?? null,
            'is_online'  => (bool) $this->is_online,
            'primary_badge' => app(BadgeService::class)->getPrimaryBadge($this->id),
            'vn_rank'    => $this->vn_rank ?? null,
            'vndupr_score' => $vnduprScore?->score_value ?? null,
            'win_rate'   => $stats['win_rate'] ?? 0.0,
            'total_matches' => $stats['total_matches'] ?? 0,
            'distance'    => $this->when(isset($this->distance), round($this->distance, 1)),
            'latitude'    => $this->latitude ?? null,
            'longitude'   => $this->longitude ?? null,
            'sports'      => $this->whenLoaded('sports', fn() =>
                UserSportResource::collection($this->sports)
            ),
            'clubs'       => $this->whenLoaded('clubs', fn() => $this->clubs->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'logo_url' => $c->logo_url,
            ])),
            'is_follow' => $isFollow,
            'marker_type' => 'user',
        ];
    }
}
