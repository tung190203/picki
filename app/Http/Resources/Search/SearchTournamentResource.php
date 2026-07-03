<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');
        $club = $this->whenLoaded('club');
        $participants = $this->whenLoaded('participants');
        $teams = $this->whenLoaded('teams');
        $tournamentStaffs = $this->whenLoaded('tournamentStaffs');

        $participantsCount = (int) ($this->participants_count ?? $participants?->count() ?? 0);

        return [
            'id'             => $this->id,
            'club_id'       => $this->club_id,
            'is_private'    => (bool) $this->is_private,
            'name'           => $this->name,
            'type'           => 'tournament',
            'poster'         => $this->poster_url,
            'description'    => $this->description,
            'sport'          => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'start_date'     => $this->start_date,
            'starts_at'      => $this->start_date,
            'start_time'     => $this->start_date ? \Carbon\Carbon::parse($this->start_date)->format('H:i') : null,
            'end_date'       => $this->end_date,
            'status'         => $this->status,
            'is_completed'   => $this->is_completed,
            'has_fee'        => (bool) $this->has_fee,
            'fee_amount'     => $this->has_fee ? (float) $this->fee_amount : null,
            'min_level'      => $this->min_level,
            'max_level'      => $this->max_level,
            'max_players'    => $this->max_player,
            'max_team'       => $this->max_team,
            'participated_team' => $teams ? $teams->count() : 0,
            'slot_status'    => $this->computeSlotStatus(),
            // Nested competition_location
            'competition_location' => $location ? [
                'id'       => $location->id,
                'name'     => $location->name,
                'address'  => $location->address,
                'latitude' => $location->latitude,
                'longitude'=> $location->longitude,
            ] : null,
            // Flat geo fields
            'location_name'  => $location?->name,
            'address'        => $location?->address,
            'lat'            => $location?->latitude,
            'lng'            => $location?->longitude,
            // Nested club
            'club' => $club ? [
                'id'   => $club->id,
                'name' => $club->name,
            ] : null,
            // Participated teams
            'participated_teams' => $teams ? $teams->map(fn($team) => [
                'id'    => $team->id,
                'name'  => $team->name,
                'avatar'=> $team->avatar,
            ])->toArray() : [],
            'tournamentStaff' => $tournamentStaffs ? $tournamentStaffs
                ->filter(fn($s) => (int) ($s->pivot->role ?? null) === \App\Models\TournamentStaff::ROLE_ORGANIZER)
                ->map(fn($s) => [
                    'user_id'    => $s->id,
                    'full_name'  => $s->full_name,
                    'avatar_url' => $s->avatar_url,
                ])->values()->toArray() : [],
            'tournamentParticipants' => $participants ? $participants->map(fn($p) => [
                'id'         => $p->id,
                'user_id'    => $p->user_id,
                'full_name'  => $p->user?->full_name,
                'avatar_url' => $p->user?->avatar_url,
                'is_confirmed' => (bool) $p->is_confirmed,
            ])->toArray() : [],
            // Created by
            'created_by' => $this->whenLoaded('createdBy', fn() => [
                'id' => $this->createdBy->id,
                'full_name' => $this->createdBy->full_name,
                'avatar_url' => $this->createdBy->avatar_url,
            ]),
            'distance'     => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'  => 'tournament',
            // Membership — use preloaded batch data to avoid N+1
            'is_joined'    => $this->preloaded_is_joined ?? $this->isJoinedBy(auth()->id()),
            'is_registered' => $this->preloaded_is_registered ?? $this->isRegisteredBy(auth()->id())
        ];
    }

    private function computeSlotStatus(): string
    {
        $max = (int) $this->max_player;
        $current = (int) ($this->participants_count ?? $this->participants?->count() ?? 0);
        $remaining = $max - $current;

        return $remaining > 0 ? 'con_trong' : 'da_day';
    }
}
