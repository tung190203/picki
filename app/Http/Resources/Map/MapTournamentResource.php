<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');
        $club = $this->whenLoaded('club');
        $participants = $this->whenLoaded('participants');
        $createdBy = $this->whenLoaded('createdBy');

        $participantsCount = (int) ($this->participants_count ?? $participants?->count() ?? 0);

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => 'tournament',
            'poster'       => $this->poster_url,
            'description'  => $this->description,
            'start_date'   => $this->start_date,
            'starts_at'    => $this->start_date,
            'start_time'   => $this->start_date ? \Carbon\Carbon::parse($this->start_date)->format('H:i') : null,
            'end_date'     => $this->end_date,
            'status'       => $this->status,
            'has_fee'      => $this->has_fee,
            'fee_amount'   => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'  => $this->max_player,
            'participants_count' => $participantsCount,
            'joined_count' => $participantsCount,
            'sport'        => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            // Nested competition_location
            'competition_location' => $location ? [
                'id'       => $location->id,
                'name'     => $location->name,
                'latitude' => $location->latitude,
                'longitude'=> $location->longitude,
                'address'  => $location->address,
            ] : null,
            // Flat geo fields
            'location_name' => $location?->name,
            'address'       => $location?->address,
            // Nested club
            'club' => $club ? [
                'id'            => $club->id,
                'name'          => $club->name,
                'logo_url'      => $club->logo_url,
                'members_count' => (int) ($club->members_count ?? 0),
            ] : null,
            // Creator
            'created_by' => $createdBy ? [
                'id'         => $createdBy->id,
                'name'       => $createdBy->full_name,
                'avatar_url' => $createdBy->avatar_url,
                'gender'     => $createdBy->gender,
            ] : null,
            'slot_status'   => $this->computeSlotStatus(),
            'distance'      => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'   => 'tournament',
            // Membership
            'is_joined'     => $this->isJoinedBy(auth()->id()),
            'is_registered' => $this->isRegisteredBy(auth()->id()),
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
