<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return  [
            'id' => $this->id,
            'poster' => $this->poster_url,
            'sport_id' => $this->sport_id,
            'sport' => $this->whenLoaded('sport', function () {
                return [
                    'id' => $this->sport->id,
                    'name' => $this->sport->name,
                    'icon' => $this->sport->icon,
                ];
            }),
            'name' => $this->name,
            'competition_location' => new CompetitionLocationResource($this->whenLoaded('competitionLocation')),
            'start_date' => $this->start_date,
            // 'end_date' => $this->end_date,
            'registration_open_at' => $this->registration_open_at,
            'registration_closed_at' => $this->registration_closed_at,
            'early_registration_deadline' => $this->early_registration_deadline,
            'duration' => $this->duration,
            'enable_dupr' => $this->enable_dupr,
            'enable_vndupr' => $this->enable_vndupr,
            'min_level' => $this->min_level,
            'max_level' => $this->max_level,
            'age_group' => $this->age_group,
            'age_group_text' => $this->age_group_text,
            'gender_policy' => $this->gender_policy,
            'gender_policy_text' => $this->gender_policy_text,
            'participant' => $this->participant,
            'max_team' => $this->max_team,
            'player_per_team' => $this->player_per_team,
            'fee' => $this->fee,
            'standard_fee_amount' => $this->standard_fee_amount,
            'is_private' => $this->is_private,
            'is_public_branch' => $this->is_public_branch,
            'is_own_score' => $this->is_own_score,
            'auto_approve' => $this->auto_approve,
            'status' => $this->status,
            'status_text' => $this->status_text,
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
            'tournament_staff' => TournamentStaffResource::collection($this->whenLoaded('tournamentStaffs')),
            'tournament_participants' => $this->whenLoaded('participants', function() {
                return ParticipantResource::collection($this->participants);
            }),
            'tournament_types' => TournamentTypeResource::collection($this->whenLoaded('tournamentTypes')) ?? [],
            'is_joined' => $this->participants
                ? $this->participants->contains('user_id', auth()->id())
                : false,
            'is_confirmed_by_organizer' =>
                (bool) $this->participants?->firstWhere('user_id', auth()->id())?->is_confirmed,
            'is_invite_by_organizer' => (bool) $this->participants?->firstWhere('user_id', auth()->id())?->is_invite_by_organizer
        ];
    }
}
