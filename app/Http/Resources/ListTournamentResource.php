<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListTournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participants = $this->whenLoaded('participants');

        return  [
            'id' => $this->id,
            'poster' => $this->poster_url,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'competition_location' => new CompetitionLocationResource($this->whenLoaded('competitionLocation')),
            'description' => $this->description,
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->full_name,
                ];
            }),
            'club' => $this->whenLoaded('club', function () {
                return [
                    'id' => $this->club->id,
                    'name' => $this->club->name,
                ];
            }),
            'is_joined' => $participants && auth()->check()
                ? ($participants->contains('user_id', auth()->id()) ?? false)
                : false,
        ];
    }
}
