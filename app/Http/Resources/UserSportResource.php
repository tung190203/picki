<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = ['personal_score', 'dupr_score', 'vndupr_score'];

        $scores = $this->relationLoaded('scores')
            ? $this->scores
            : collect();

        $formattedScores = [];
        foreach ($types as $type) {
            $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
            $scoreValue = $latestScore ? $latestScore->score_value : 0;
            $formattedScores[$type] = number_format($scoreValue, 3);
        }

        // Note: getSportStats is intentionally omitted here — stats computation is
        // expensive and already handled via getBatchSportStats at the controller level
        // for list/search contexts. Callers needing stats should use batch-loaded data.

        return [
            'sport_id'   => $this->sport_id,
            'sport_icon' => $this->relationLoaded('sport') ? optional($this->sport)->icon : null,
            'sport_name' => $this->relationLoaded('sport') ? optional($this->sport)->name : null,
            'scores'     => $formattedScores,
        ];
    }
}
