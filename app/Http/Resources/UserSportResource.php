<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserSportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $types = ['personal_score', 'dupr_score', 'vndupr_score'];
        $scores = $this->relationLoaded('scores') ? $this->scores : collect();

        $formattedScores = [];
        foreach ($types as $type) {
            $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
            $scoreValue = $latestScore ? $latestScore->score_value : 0;
            $formattedScores[$type] = number_format($scoreValue, 3);
        }

        $stats = User::getSportStats($this->user_id, $this->sport_id);

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
}
