<?php

namespace App\Http\Resources;

use App\Traits\FormatsTeamMembers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchesResource extends JsonResource
{
    use FormatsTeamMembers;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group?->name,
            'name_of_match' => $this->name_of_match,
            'round' => $this->round,
            'next_match_id' => $this->next_match_id,
            'next_position' => $this->next_position,
            'loser_next_match_id' => $this->loser_next_match_id,
            'loser_next_position' => $this->loser_next_position,
            'scheduled_at' => $this->scheduled_at,
            'status' => $this->status,
            'home_team' => $this->whenLoaded('homeTeam', function () {
                return [
                    'id' => $this->homeTeam->id,
                    'name' => $this->homeTeam->name,
                    'members' => self::formatMembers(
                        $this->homeTeam->members,
                        $this->tournamentType?->tournament_id,
                        null,
                        'tournament'
                    ),
                ];
            }),
            'away_team' => $this->whenLoaded('awayTeam', function () {
                return [
                    'id' => $this->awayTeam->id,
                    'name' => $this->awayTeam->name,
                    'members' => self::formatMembers(
                        $this->awayTeam->members,
                        $this->tournamentType?->tournament_id,
                        null,
                        'tournament'
                    ),
                ];
            }),
            'leg' => $this->leg,
            'is_bye' => $this->is_bye,
            'is_loser_bracket' => $this->is_loser_bracket,
            'is_third_place' => $this->is_third_place,
            'court' => $this->court,
            'winner_id' => $this->winner_id,

            'referee' => $this->whenLoaded('referee', function () {
                return [
                    'id' => $this->referee->id,
                    'name' => $this->referee->full_name,
                ];
            }),
        ];
    }
}
