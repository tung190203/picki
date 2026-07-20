<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = ['personal_score', 'dupr_score', 'vndupr_score', 'spcn_score'];

        $scores = $this->relationLoaded('scores')
            ? $this->scores
            : collect();

        $formattedScores = [];
        foreach ($types as $type) {
            $scoreValue = $this->getScoreValue($type, $scores);
            $formattedScores[$type] = $scoreValue !== null ? number_format($scoreValue, 3) : null;
        }

        // Use preloaded batch stats if available (set by SearchV2Controller), otherwise fall back.
        // Supports two formats:
        //   - Flat array (set directly with stats of this sport): used by HomeController/UserController
        //   - Nested array keyed by sport_id (set by SearchV2Controller::loadBatchUserSportStats)
        $preloaded = $this->preloaded_sport_stats ?? [];
        if (isset($preloaded[$this->sport_id])) {
            $stats = $preloaded[$this->sport_id];
        } elseif (isset($preloaded['total_matches'])) {
            $stats = $preloaded;
        } else {
            $stats = [
                'total_matches' => 0,
                'total_tournaments' => 0,
                'total_mini_tournaments' => 0,
                'total_prizes' => 0,
                'win_rate' => 0.0,
                'performance' => 0,
            ];
        }

        return [
            'sport_id'   => $this->sport_id,
            'sport_icon' => $this->relationLoaded('sport') ? optional($this->sport)->icon : null,
            'sport_name' => $this->relationLoaded('sport') ? optional($this->sport)->name : null,
            'scores'     => $formattedScores,
            'total_matches' => $stats['total_matches'],
            'total_tournaments' => $stats['total_tournaments'],
            'total_mini_tournaments' => $stats['total_mini_tournaments'],
            'total_prizes' => $stats['total_prizes'],
            'win_rate' => $stats['win_rate'],
            'performance' => $stats['performance'],
        ];
    }

    private function getScoreValue(string $type, $scores)
    {
        $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();

        if (!$latestScore) {
            // dupr_score and spcn_score return null if no score
            if (in_array($type, ['dupr_score', 'spcn_score'])) {
                return null;
            }
            return 0;
        }

        return (float) $latestScore->score_value;
    }
}
