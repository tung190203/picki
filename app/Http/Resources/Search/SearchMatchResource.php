<?php

namespace App\Http\Resources\Search;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'poster'         => $this->poster && !str_starts_with($this->poster, 'http')
                ? asset('storage/' . ltrim($this->poster, '/'))
                : $this->poster,
            'sport'          => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'start_time'     => $this->start_time,
            'start_hour'     => $this->start_time ? \Carbon\Carbon::parse($this->start_time)->format('H:i') : null,
            'end_time'       => $this->end_time,
            'status'         => $this->status,
            'has_fee'        => (bool) $this->has_fee,
            'fee_amount'     => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'    => $this->max_players,
            'participants_count' => (int) ($this->participants_count ?? $this->participants?->count() ?? 0),
            'slot_status'    => $this->computeSlotStatus(),
            'location_name'  => $location?->name,
            'address'        => $location?->address,
            'lat'            => $location?->latitude,
            'lng'            => $location?->longitude,
            'distance'       => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'    => 'mini_tournament',
        ];
    }

    private function computeSlotStatus(): string
    {
        $max = (int) $this->max_players;
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
