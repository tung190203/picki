<?php

namespace App\Http\Resources;

use App\Models\MiniTournamentStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListMiniTournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participants = $this->relationLoaded('participants') ? $this->participants : collect();
        
        $posterUrl = $this->poster;
        if ($posterUrl && !str_starts_with($posterUrl, 'http')) {
            $posterUrl = asset('storage/' . ltrim($posterUrl, '/'));
        }
        
        $data = [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'poster' => $posterUrl,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration' => $this->duration,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'name' => $this->name,
            'description' => $this->description,
            'competition_location' => new CompetitionLocationResource($this->whenLoaded('competitionLocation')),
            'club' => ($this->whenLoaded('club') && $this->club && $this->club->is_public !== false)
                ? new \App\Http\Resources\ClubResource($this->club)
                : null,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'has_fee' => $this->has_fee,
            'fee_amount' => $this->fee_amount,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'max_players' => $this->max_players,
            'play_mode' => $this->play_mode,
            'format' => $this->format,
            'apply_rule' => $this->apply_rule,
            'staff' => $this->whenLoaded('staff', function () {
                return $this->staff
                    ->groupBy(fn($staff) => MiniTournamentStaff::getRoleText( $staff->pivot->role))
                    ->map(fn($group) => MiniTournamentStaffResource::collection($group));
            }),
            'participants' => MiniParticipantResource::collection($participants),
            'matches' => $this->whenLoaded('matches', function () {
                return MiniMatchResource::collection($this->matches);
            }),
            'all_users' => UserListResource::collection($this->all_users ?? collect()),

            // Club fund integration
            'use_club_fund' => $this->use_club_fund,
            'included_in_club_fund' => $this->included_in_club_fund,
            'club_fund_collection_id' => $this->club_fund_collection_id,
        ];

        // Include game rule fields only if apply_rule is true
        if ($this->apply_rule) {
            $data['set_number'] = $this->set_number;
            $data['base_points'] = $this->base_points;
            $data['points_difference'] = $this->points_difference;
            $data['max_points'] = $this->max_points;
        }

        return $data;
    }
}
