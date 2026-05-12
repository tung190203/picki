<?php

namespace App\Services;

use App\Http\Resources\Map\MapClubResource;
use App\Http\Resources\Map\MapCourtResource;
use App\Http\Resources\Map\MapMiniTournamentResource;
use App\Http\Resources\Map\MapTournamentResource;
use App\Http\Resources\Map\MapUserResource;
use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SearchV2Service
{
    public function buildWeekdayGroups(Collection $items, string $dateField): array
    {
        $today = now()->dayOfWeek;

        $grouped = $items->groupBy(function ($item) use ($dateField) {
            $date = $item->{$dateField};
            return Carbon::parse($date)->dayName;
        });

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $startIndex = $today - 1;

        if ($startIndex > 0) {
            $dayOrder = array_merge(
                array_slice($dayOrder, $startIndex),
                array_slice($dayOrder, 0, $startIndex)
            );
        }

        $result = [];
        foreach ($dayOrder as $dayName) {
            $dayItems = $grouped->get($dayName, collect());
            $result[] = [
                'day'       => $dayName,
                'is_today'  => $dayName === now()->dayName,
                'count'     => $dayItems->count(),
                'items'     => $dayItems->values()->toArray(),
            ];
        }

        return $result;
    }

    public function resolveResourceClass(string $tab): string
    {
        return match ($tab) {
            SearchFilterConfig::TAB_MATCH      => MapMiniTournamentResource::class,
            SearchFilterConfig::TAB_TOURNAMENT => MapTournamentResource::class,
            SearchFilterConfig::TAB_CLUB       => MapClubResource::class,
            SearchFilterConfig::TAB_USER       => MapUserResource::class,
            SearchFilterConfig::TAB_COURT      => MapCourtResource::class,
            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };
    }

    public function resolveListResourceClass(string $tab): string
    {
        return match ($tab) {
            SearchFilterConfig::TAB_MATCH      => \App\Http\Resources\Search\SearchMatchResource::class,
            SearchFilterConfig::TAB_TOURNAMENT => \App\Http\Resources\Search\SearchTournamentResource::class,
            SearchFilterConfig::TAB_CLUB       => \App\Http\Resources\Search\SearchClubResource::class,
            SearchFilterConfig::TAB_USER       => \App\Http\Resources\Search\SearchPlayerResource::class,
            SearchFilterConfig::TAB_COURT     => \App\Http\Resources\Search\SearchCourtResource::class,
            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };
    }

    public function resolveModel(string $tab): string
    {
        return match ($tab) {
            SearchFilterConfig::TAB_MATCH      => MiniTournament::class,
            SearchFilterConfig::TAB_TOURNAMENT => Tournament::class,
            SearchFilterConfig::TAB_CLUB       => Club::class,
            SearchFilterConfig::TAB_USER       => User::class,
            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };
    }

    public function resolveDateField(string $tab): string
    {
        return match ($tab) {
            SearchFilterConfig::TAB_MATCH      => 'start_time',
            SearchFilterConfig::TAB_TOURNAMENT => 'start_date',
            SearchFilterConfig::TAB_CLUB       => 'created_at',
            SearchFilterConfig::TAB_USER        => 'created_at',
            SearchFilterConfig::TAB_COURT      => 'created_at',
            default => 'created_at',
        };
    }

    public function resolveGeoMethod(string $tab): ?string
    {
        return match ($tab) {
            SearchFilterConfig::TAB_USER,
            SearchFilterConfig::TAB_CLUB => 'nearBy',
            SearchFilterConfig::TAB_MATCH,
            SearchFilterConfig::TAB_TOURNAMENT => 'nearBy',
            default => null,
        };
    }

    public function computeBounds(Collection $items, string $tab): ?array
    {
        if ($items->isEmpty()) {
            return null;
        }

        $lats = [];
        $lngs = [];

        foreach ($items as $item) {
            if ($tab === SearchFilterConfig::TAB_COURT) {
                $lat = $item->latitude ?? null;
                $lng = $item->longitude ?? null;
            } else {
                $lat = $item->latitude ?? $item->competitionLocation?->latitude ?? null;
                $lng = $item->longitude ?? $item->competitionLocation?->longitude ?? null;
            }
            if ($lat !== null && $lng !== null) {
                $lats[] = $lat;
                $lngs[] = $lng;
            }
        }

        if (empty($lats)) {
            return null;
        }

        return [
            'minLat' => min($lats),
            'maxLat' => max($lats),
            'minLng' => min($lngs),
            'maxLng' => max($lngs),
        ];
    }
}
