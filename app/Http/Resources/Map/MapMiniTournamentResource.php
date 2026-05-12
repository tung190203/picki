<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapMiniTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'poster'       => $this->poster && !str_starts_with($this->poster, 'http')
                ? asset('storage/' . ltrim($this->poster, '/'))
                : $this->poster,
            'lat'          => $location?->latitude,
            'lng'          => $location?->longitude,
            'start_time'   => $this->start_time,
            'start_hour'   => $this->start_time ? \Carbon\Carbon::parse($this->start_time)->format('H:i') : null,
            'status'       => $this->status,
            'has_fee'      => $this->has_fee,
            'fee_amount'   => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'  => $this->max_players,
            'sport'        => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'location_name' => $location?->name,
            'address'       => $location?->address,
            'slot_status'   => $this->computeSlotStatus(),
            'distance'      => $this->when(isset($this->distance), (int) round($this->distance)),
            'is_joined'     => auth()->check()
                ? (($this->relationLoaded('participants') ? $this->participants : collect())
                    ->contains('user_id', auth()->id()))
                : false,
            'marker_type'   => 'mini_tournament',
        ];
    }

    private function computeSlotStatus(): string
    {
        $max = (int) $this->max_players;
        $current = (int) ($this->participants_count ?? $this->participants?->count() ?? 0);
        $remaining = $max - $current;

        return $remaining > 0 ? 'con_trong' : 'da_day';
    }
}
