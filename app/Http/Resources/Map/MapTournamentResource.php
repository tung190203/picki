<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'poster'       => $this->poster_url,
            'lat'          => $location?->latitude,
            'lng'          => $location?->longitude,
            'start_date'   => $this->start_date,
            'start_time'   => $this->start_date ? \Carbon\Carbon::parse($this->start_date)->format('H:i') : null,
            'status'       => $this->status,
            'has_fee'      => $this->has_fee,
            'fee_amount'   => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'  => $this->max_player,
            'sport'        => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'location_name' => $location?->name,
            'address'       => $location?->address,
            'slot_status'   => $this->computeSlotStatus(),
            'distance'      => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'   => 'tournament',
        ];
    }

    private function computeSlotStatus(): string
    {
        $max = (int) $this->max_player;
        $current = (int) ($this->participants_count ?? $this->participants?->count() ?? 0);
        $remaining = $max - $current;

        if ($remaining <= 0) {
            return 'full_slot';
        }
        if ($remaining === 1) {
            return 'one_slot';
        }
        return 'two_slot';
    }
}
