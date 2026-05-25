<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubLeaderboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resource wraps the data in $this->resource, access it as array
        $data = $this->resource;

        return [
            'rank' => $data['rank'],
            'member_id' => $data['member_id'],
            'user' => [
                'id' => $data['user']->id,
                'full_name' => $data['user']->full_name,
                'avatar_url' => $data['user']->avatar_url,
                'visibility' => $data['user']->visibility,
                'is_verify' => (bool) (($data['user']->total_matches_has_anchor ?? 0) >= 10),
                'is_anchor' => (bool) ($data['user']->is_anchor ?? false),
            ],
            'vndupr_score' => round($data['vndupr_score'], 3),
            'all_time_stats' => [
                'matches_played' => $data['all_time_stats']['matches_played'],
                'wins' => $data['all_time_stats']['wins'],
                'losses' => $data['all_time_stats']['losses'],
                'win_rate' => round($data['all_time_stats']['win_rate'], 2),
                'score_change' => round($data['all_time_stats']['score_change'], 3),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'message' => 'Lấy bảng xếp hạng thành công',
        ];
    }
}
