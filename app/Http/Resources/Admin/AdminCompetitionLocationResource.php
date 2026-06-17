<?php

namespace App\Http\Resources\Admin;

use App\Models\MiniTournament;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCompetitionLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();

        $activeMiniTournaments = $this->relationLoaded('miniTournaments')
            ? $this->miniTournaments->filter(fn($t) =>
                $t->status === MiniTournament::STATUS_OPEN
                && ($t->end_time === null || $t->end_time->gte($now))
            )
            : collect();

        $activeTournaments = $this->relationLoaded('tournaments')
            ? $this->tournaments->filter(fn($t) =>
                $t->status === Tournament::OPEN
                && ($t->end_date === null || Carbon::parse($t->end_date)->endOfDay()->gte($now))
            )
            : collect();

        $activeMatchesCount = $activeMiniTournaments->count();
        $activeTournamentsCount = $activeTournaments->count();

        $summary = sprintf(
            'Đang có %d kèo - %d giải đấu',
            $activeMatchesCount,
            $activeTournamentsCount
        );

        return [
            'id' => $this->id,
            'location' => new \App\Http\Resources\LocationResource($this->whenLoaded('location')),
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image' => $this->image,
            'address' => $this->address,
            'phone' => $this->phone,
            'opening_time' => $this->opening_time,
            'closing_time' => $this->closing_time,
            'note_booking' => $this->note_booking,
            'website' => $this->website,
            'sports' => \App\Http\Resources\SportResource::collection($this->whenLoaded('sports')),
            'yard_types' => $this->whenLoaded('competitionLocationYards', function () {
                return $this->competitionLocationYards->pluck('yard_type')->unique()->map(function ($type) {
                    return [
                        'type' => $type,
                        'name' => match ($type) {
                            \App\Models\CompetitionLocationYard::TYPE_INDOOR => 'Trong nhà',
                            \App\Models\CompetitionLocationYard::TYPE_OUTDOOR => 'Ngoài trời',
                            \App\Models\CompetitionLocationYard::TYPE_PRIVATE_RENTAL => 'Thuê riêng',
                            \App\Models\CompetitionLocationYard::TYPE_PAY_FEE => 'Đóng phí',
                            \App\Models\CompetitionLocationYard::TYPE_ROOF => 'Mái che',
                            default => 'Unknown',
                        },
                    ];
                })->values();
            }),
            'facilities' => \App\Http\Resources\FacilityResource::collection($this->whenLoaded('facilities')),

            'status' => $this->status,
            'is_banned' => (bool) ($this->is_banned ?? false),

            'active_matches_count' => $activeMatchesCount,
            'active_tournaments_count' => $activeTournamentsCount,

            'active_tournaments' => $activeTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => 'tournament',
                'status' => $t->status,
                'start_date' => $t->start_date
                    ? Carbon::parse($t->start_date)->format('Y-m-d')
                    : null,
                'created_by' => new \App\Http\Resources\UserResource($t->creator),
            ])->values(),

            'active_mini_tournaments' => $activeMiniTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'start_date' => $t->start_time?->format('Y-m-d'),
                'created_by' => new \App\Http\Resources\UserResource($t->creator),
            ])->values(),

            'summary' => $summary,

            'created_by' => $this->whenLoaded('creator', fn() => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name,
                'avatar_url' => $this->creator->avatar_url,
            ] : null),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
