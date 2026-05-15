<?php

namespace App\Http\Resources\Search;

use App\Http\Resources\UserResource;
use App\Models\MiniTournamentStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->whenLoaded('competitionLocation');
        $participants = $this->whenLoaded('participants');
        $staff = $this->whenLoaded('staff');

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => 'mini',
            'poster'         => $this->poster && !str_starts_with($this->poster, 'http')
                ? asset('storage/' . ltrim($this->poster, '/'))
                : $this->poster,
            'sport'          => $this->whenLoaded('sport', fn() => [
                'id'   => $this->sport->id,
                'name' => $this->sport->name,
                'icon' => $this->sport->icon,
            ]),
            'start_time'     => $this->start_time,
            'starts_at'      => $this->start_time,
            'end_time'       => $this->end_time,
            'duration_minutes' => (int) ($this->duration ?? 0),
            'status'         => $this->status,
            'has_fee'        => (bool) $this->has_fee,
            'fee_amount'     => $this->has_fee ? (float) $this->fee_amount : null,
            'max_players'    => $this->max_players,
            'participants_count' => (int) ($this->participants_count ?? $participants?->where('is_confirmed', true)->count() ?? 0),
            'joined_count'      => (int) ($this->participants_count ?? $participants?->where('is_confirmed', true)->count() ?? 0),
            'slot_status'    => $this->computeSlotStatus(),
            'competition_location' => $location ? [
                'id'       => $location->id,
                'name'     => $location->name,
                'address'  => $location->address,
                'latitude' => $location->latitude,
                'longitude'=> $location->longitude,
            ] : null,
            'location_name'  => $location?->name,
            'address'        => $location?->address,
            // Participants
            'participants'   => $participants ? $participants->map(fn($p) => [
                'id'         => $p->id,
                'user_id'    => $p->user_id,
                'full_name'  => $p->user?->full_name,
                'avatar_url' => $p->user?->avatar_url,
                'is_confirmed' => (bool) $p->is_confirmed,
                'is_guest'   => (bool) $p->is_guest,
            ])->toArray() : [],
            'staff' => [
                'organizer' => $staff ? $staff
                    ->filter(fn($s) => (int) ($s->pivot->role ?? null) === MiniTournamentStaff::ROLE_ORGANIZER)
                    ->map(fn($s) => [
                        'mini_tournament_id' => (int) ($s->pivot->mini_tournament_id ?? $this->id),
                        'user_id' => $s->id,
                        'user' => new UserResource($s),
                    ])->values()->toArray() : [],
            ],
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'is_private'   => (bool) $this->is_private,
            'min_rating'   => $this->min_rating,
            'max_rating'   => $this->max_rating,
            'is_dupr'      => (bool) ($this->is_dupr ?? false),
            'distance'     => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'  => 'mini_tournament',
            // Membership
            'is_joined'    => $this->isJoinedBy(auth()->id()),
            'is_registered' => $this->isRegisteredBy(auth()->id()),
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
