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
            'competition_location_id' => $this->competition_location_id,
            'competition_location' => $this->whenLoaded('competitionLocation')
                ? new CompetitionLocationResource($this->competitionLocation)
                : null,
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
            'has_financial_management' => $this->has_financial_management,
            'has_fee' => $this->has_fee,
            'fee_amount' => $this->fee_amount,
            'fee_per_person' => $this->fee_per_person,
            'total_fee_expected' => $this->total_fee_expected,
            'auto_split_fee' => $this->auto_split_fee,
            'fee_description' => $this->fee_description,
            'qr_code_url' => $this->qr_code_url,
            'is_private' => $this->is_private,
            'creator_join' => $this->creator_join,
            'is_public_branch' => $this->is_public_branch,
            'is_own_score' => $this->is_own_score,
            'auto_approve' => $this->auto_approve,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'is_completed' => (int) $this->status === 3,
            'description' => $this->description,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'club' => $this->whenLoaded('club', function () {
                return [
                    'id' => $this->club->id,
                    'name' => $this->club->name,
                    'address' => $this->club->address,
                    'quantity_members' => $this->club->members_count ?? $this->club->members()->count(),
                ];
            }),
            'tournament_staff' => TournamentStaffResource::collection($this->whenLoaded('tournamentStaffs')),
            'tournament_participants' => $this->whenLoaded('participants', function() {
                return ParticipantResource::collection($this->participants);
            }),
            'tournament_types' => TournamentTypeResource::collection($this->whenLoaded('tournamentTypes')) ?? [],
            'is_joined' => $this->whenLoaded('participants') && auth()->check()
                ? ($this->participants?->contains('user_id', auth()->id()) ?? false)
                : false,
            'is_confirmed_by_organizer' => $this->whenLoaded('participants') && auth()->check()
                ? (bool) ($this->participants?->firstWhere('user_id', auth()->id())?->is_confirmed ?? false)
                : false,
            'is_invite_by_organizer' => $this->whenLoaded('participants') && auth()->check()
                ? (bool) ($this->participants?->firstWhere('user_id', auth()->id())?->is_invite_by_organizer ?? false)
                : false,
            'my_tournament_stats' => $this->whenLoaded('participants', function () {
                $participant = $this->participants?->firstWhere('user_id', auth()->id());
                if (!$participant) {
                    return null;
                }
                return [
                    'rating_before' => $participant->rating_before ? (float) $participant->rating_before : null,
                    'rating_after' => $participant->rating_after ? (float) $participant->rating_after : null,
                    'rank_before' => $participant->rank_before,
                    'rank_after' => $participant->rank_after,
                    'rank_change' => $participant->rank_change,
                ];
            }),
            'zalo_link' => $this->zalo_link,
        ];
    }
}
