<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniMatchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'mini_match_id'   => $this->mini_match_id,
            'team' => (new MiniTeamResource($this->whenLoaded('team')))->forMiniTournament(
                $this->miniMatch?->mini_tournament_id
            ),
            'score'           => $this->score,
            'won_set'       => $this->won_set,
        ];
    }
}
