<?php

namespace App\Http\Resources\Search;

use App\Http\Resources\UserResource;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchCourtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();

        $activeMiniTournaments = $this->relationLoaded('miniTournaments')
            ? $this->miniTournaments->filter(function ($t) use ($now) {
                if ($t->status !== MiniTournament::STATUS_OPEN) {
                    return false;
                }
                if ($t->session_status === MiniTournament::SESSION_STATUS_FINISHED) {
                    return false;
                }
                if ($t->relationLoaded('matches') && $t->is_completed) {
                    return false;
                }
                if ($t->end_time !== null) {
                    return $t->end_time->gte($now);
                }
                return $t->start_time === null || $t->start_time->gte($now->copy()->startOfDay());
            })->values()
            : collect();

        $activeTournaments = $this->relationLoaded('tournaments')
            ? $this->tournaments->filter(function ($t) use ($now) {
                if ($t->status !== Tournament::OPEN) {
                    return false;
                }
                if ($t->relationLoaded('tournamentTypes') && $t->is_completed) {
                    return false;
                }
                if ($t->end_date !== null) {
                    $end = Carbon::parse($t->end_date);
                    if ($end->format('H:i:s') === '00:00:00') {
                        return $end->endOfDay()->gte($now);
                    }
                    return $end->gte($now);
                }
                return $t->start_date === null || Carbon::parse($t->start_date)->endOfDay()->gte($now);
            })->values()
            : collect();

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'image'           => $this->image,
            'address'         => $this->address,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'phone'           => $this->phone,
            'opening_time'    => $this->opening_time,
            'closing_time'    => $this->closing_time,
            'number_of_yards' => $this->whenLoaded('competitionLocationYards', fn() => $this->competitionLocationYards->count()),
            'sports'          => $this->whenLoaded('sports', fn() => $this->sports->map(fn($s) => [
                'id'   => $s->id,
                'name' => $s->name,
                'icon' => $s->icon,
            ])),
            'facilities'      => $this->whenLoaded('facilities', fn() => $this->facilities->map(fn($f) => [
                'id'          => $f->id,
                'name'        => $f->name,
                'description' => $f->description,
            ])),
            'distance'        => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'     => 'court',

            // Thống kê kèo đấu & giải đấu sắp và đang diễn ra
            'active_matches_count' => $this->active_matches_count ?? $activeMiniTournaments->count(),
            'active_tournaments_count' => $this->active_tournaments_count ?? $activeTournaments->count(),
            'active_tournaments' => $activeTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => 'tournament',
                'status' => $t->status,
                'start_date' => $t->start_date
                    ? Carbon::parse($t->start_date)->format('Y-m-d')
                    : null,
                'created_by' => $t->creator ? new UserResource($t->creator) : null,
            ])->values(),
            'active_mini_tournaments' => $activeMiniTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'start_date' => $t->start_time?->format('Y-m-d'),
                'created_by' => $t->creator ? new UserResource($t->creator) : null,
            ])->values(),
        ];
    }
}
