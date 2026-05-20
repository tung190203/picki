<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class MapUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scores = $this->whenLoaded('sports',
            fn() => $this->sports->firstWhere('sport_id', 1)?->scores ?? collect(),
            fn() => collect()
        );

        $vnduprScore = $scores->firstWhere('score_type', 'vndupr_score');
        $duprScore   = $scores->firstWhere('score_type', 'dupr_score');

        // Use preloaded batch stats if available (set by SearchV2Controller::mapResponse),
        // otherwise fall back to the per-row query for backward compatibility.
        if (isset($this->preloaded_sport_stats)) {
            $stats = $this->preloaded_sport_stats;
        } else {
            $stats = User::getSportStats($this->id, 1, false);
        }

        return [
            'id'           => $this->id,
            'full_name'    => $this->full_name,
            'avatar_url'   => $this->avatar_url,
            'latitude'   => $this->latitude,
            'longitude'  => $this->longitude,
            'gender'       => $this->gender,
            'gender_text'  => $this->gender_text ?? null,
            'is_online'    => (bool) $this->is_online,
            'is_verified'  => (bool) $this->is_verified,
            'vn_rank'      => $this->vn_rank ?? null,
            'vndupr_score' => $vnduprScore?->score_value ?? null,
            'dupr_score'   => $duprScore?->score_value ?? null,
            'total_matches' => $stats['total_matches'] ?? 0,
            'win_rate'     => $stats['win_rate'] ?? 0.0,
            'distance'     => $this->when(isset($this->distance), round($this->distance, 1)),
            'clubs'        => $this->whenLoaded('clubs', fn() => $this->clubs->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'logo_url' => $c->logo_url,
            ])),
            'marker_type'  => 'user',
        ];
    }
}
