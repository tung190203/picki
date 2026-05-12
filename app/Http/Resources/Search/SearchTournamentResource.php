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
        $creator = $this->whenLoaded('createdBy');
        $participants = $this->whenLoaded('participants');

        $participantsCount = (int) ($this->participants_count ?? $participants?->count() ?? 0);

        return [
            'id'             => $this->id,
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
            'has_fee'        => (bool) $this->has_fee,
            'fee_amount'     => $this->has_fee ? (float) $this->fee_amount : null,
            'min_level'      => $this->min_level,
            'max_level'      => $this->max_level,
            'max_players'    => $this->max_player,
            'participants_count' => $participantsCount,
            'joined_count'   => $participantsCount,
            'slot_status'    => $this->computeSlotStatus(),
            // Nested competition_location
            'competition_location' => $location ? [
                'id'      => $location->id,
                'name'    => $location->name,
                'address' => $location->address,
            ] : null,
            // Flat geo fields
            'location_name'  => $location?->name,
            'address'        => $location?->address,
            'lat'            => $location?->latitude,
            'lng'            => $location?->longitude,
            // Nested club
            'club' => $club ? [
                'id'            => $club->id,
                'name'          => $club->name,
                'logo_url'      => $club->logo_url,
                'members_count' => (int) ($club->members_count ?? 0),
            ] : null,
            // Creator
            'creator' => $creator ? [
                'id'         => $creator->id,
                'full_name'  => $creator->full_name,
                'avatar_url' => $creator->avatar_url,
            ] : null,
            'distance'     => $this->when(isset($this->distance), (int) round($this->distance)),
            'marker_type'  => 'tournament',
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
