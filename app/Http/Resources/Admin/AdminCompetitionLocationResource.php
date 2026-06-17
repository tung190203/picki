<?php

namespace App\Http\Resources\Admin;

use App\Models\MiniTournament;
use App\Models\QuickMatch;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCompetitionLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeTournaments = $this->relationLoaded('tournaments')
            ? $this->tournaments->filter(fn($t) => in_array($t->status, [Tournament::OPEN, 5]))
            : collect();

        $activeMiniTournaments = $this->relationLoaded('miniTournaments')
            ? $this->miniTournaments->filter(fn($t) => in_array($t->status, [MiniTournament::STATUS_OPEN, MiniTournament::STATUS_CLOSED]))
            : collect();

        $activeQuickMatches = $this->relationLoaded('quickMatches')
            ? $this->quickMatches->filter(fn($qm) =>
                $qm->status === QuickMatch::STATUS_PENDING &&
                ($qm->scheduled_at === null || $qm->scheduled_at?->isFuture())
            )
            : collect();

        $activeMatchesCount = $activeTournaments->sum(fn($t) =>
            $t->relationLoaded('tournamentTypes')
                ? $t->tournamentTypes->sum(fn($tt) =>
                    $tt->relationLoaded('groups')
                        ? $tt->groups->sum(fn($g) =>
                            $g->relationLoaded('matches')
                                ? $g->matches->where('status', 'pending')->count()
                                : 0
                        )
                        : 0
                )
                : 0
        ) + $activeMiniTournaments->sum(fn($t) =>
            $t->relationLoaded('matches')
                ? $t->matches->whereIn('status', ['pending', 'going_on', 'waiting_confirm'])->count()
                : 0
        ) + $activeQuickMatches->count();

        $activeTournamentsCount = $activeTournaments->count() + $activeMiniTournaments->count();

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

            'active_matches_count' => $activeMatchesCount,
            'active_tournaments_count' => $activeTournamentsCount,

            'active_tournaments' => $activeTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => 'tournament',
                'status' => $this->mapTournamentStatus($t->status),
                'start_date' => $t->start_date?->format('Y-m-d'),
            ])->values(),

            'active_mini_tournaments' => $activeMiniTournaments->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $this->mapMiniTournamentStatus($t->status),
                'start_date' => $t->start_time?->format('Y-m-d'),
            ])->values(),

            'summary' => $summary,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function mapTournamentStatus(int $status): string
    {
        return match ($status) {
            Tournament::DRAFT => 'draft',
            Tournament::OPEN => 'registration_open',
            Tournament::CLOSED => 'closed',
            Tournament::CANCELLED => 'cancelled',
            5 => 'ongoing',
            default => 'upcoming',
        };
    }

    private function mapMiniTournamentStatus(int $status): string
    {
        return match ($status) {
            MiniTournament::STATUS_DRAFT => 'draft',
            MiniTournament::STATUS_OPEN => 'registration_open',
            MiniTournament::STATUS_CLOSED => 'closed',
            MiniTournament::STATUS_CANCELLED => 'cancelled',
            default => 'upcoming',
        };
    }
}
