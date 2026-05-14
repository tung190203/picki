<?php

namespace App\Http\Resources\Map;

use App\Http\Resources\UserResource;
use App\Models\MiniTournamentStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapMiniTournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');
        $creator = $this->whenLoaded('creator');
        $participants = $this->whenLoaded('participants');
        $staff = $this->whenLoaded('staff');

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => 'mini',
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
            'starts_at'    => $this->start_time,
            'end_time'    => $this->end_time,
            'duration_minutes' => (int) ($this->duration ?? 0),
            'status'       => $this->status,
            'has_fee'      => (bool) $this->has_fee,
            'fee_amount'   => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'  => $this->max_players,
            'participants_count' => (int) ($this->participants_count ?? $participants?->count() ?? 0),
            'joined_count' => (int) ($this->participants_count ?? $participants?->count() ?? 0),
            'sport'        => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'location_name' => $location?->name,
            'address'       => $location?->address,
            'latitude '           => $location?->latitude,
            'longitude'           => $location?->longitude,
            'created_by'   => $creator ? [
                'id'         => $creator->id,
                'name'       => $creator->full_name,
                'avatar_url' => $creator->avatar_url,
                'gender'     => $creator->gender,
            ] : null,
            'staff' => [
                'organizer' => $staff ? $staff
                    ->filter(fn($s) => (int) ($s->pivot->role ?? null) === MiniTournamentStaff::ROLE_ORGANIZER)
                    ->map(fn($s) => [
                        'user' => new UserResource($s->user),
                    ])->values()->toArray() : [],
            ],
            'slot_status'   => $this->computeSlotStatus(),
            'distance'      => $this->when(isset($this->distance), round($this->distance, 1)),
            'is_private'   => (bool) $this->is_private,
            'min_rating'   => $this->min_rating,
            'max_rating'   => $this->max_rating,
            'is_dupr'      => (bool) ($this->is_dupr ?? false),
            'is_joined'    => auth()->check()
                ? (($this->relationLoaded('participants') ? $this->participants : collect())
                    ->contains('user_id', auth()->id()))
                : false,
            'is_registered' => $this->isRegisteredBy(auth()->id()),
            'participants' => $participants ? $participants->map(fn($p) => [
                'id'         => $p->id,
                'user_id'    => $p->user_id,
                'full_name'  => $p->user?->full_name,
                'avatar_url' => $p->user?->avatar_url,
                'is_confirmed' => (bool) $p->is_confirmed,
                'is_guest'   => (bool) $p->is_guest,
            ])->toArray() : [],
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
