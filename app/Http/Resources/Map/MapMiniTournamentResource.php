<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapMiniTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');
        $creator = $this->whenLoaded('creator');

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'poster'       => $this->poster && !str_starts_with($this->poster, 'http')
                ? asset('storage/' . ltrim($this->poster, '/'))
                : $this->poster,
            'competition_location' => $location ? [
                'id'       => $location->id,
                'name'     => $location->name,
                'latitude' => $location->latitude,
                'longitude'=> $location->longitude,
                'address'  => $location->address,
            ] : null,
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
            'lat'           => $location?->latitude,
            'lng'           => $location?->longitude,
            'created_by'   => $creator ? [
                'id'         => $creator->id,
                'name'       => $creator->full_name,
                'avatar_url' => $creator->avatar_url,
                'gender'     => $creator->gender,
            ] : null,
            'slot_status'   => $this->computeSlotStatus(),
            'distance'      => $this->when(isset($this->distance), round($this->distance, 1)),
            'is_joined'     => auth()->check()
                ? (($this->relationLoaded('participants') ? $this->participants : collect())
                    ->contains('user_id', auth()->id()))
                : false,
            'participants' => $this->whenLoaded('participants', fn() => $this->participants->map(fn($p) => [
                'is_guest'   => (bool) $p->is_guest,
                'user'       => $p->user ? [
                    'avatar_url' => $p->user->avatar_url,
                ] : null,
            ])),
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
