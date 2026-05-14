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
use Illuminate\Support\Facades\Auth;

class SearchV2Service
{
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
