<?php

namespace App\Services\Club;

use App\Http\Resources\CompetitionLocationResource;
use App\Models\Club\Club;
use App\Models\CompetitionLocation;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClubHomeCourtService
{
    /**
     * Trả về danh sách sân nhà kèm:
     *  - distance_km: tính từ anchor (CLB.lat/lng -> user lat/lng -> null)
     *  - events_hosted_count: đếm mini-tournament + tournament đã finished của CLB tại sân đó
     */
    public function enrichCollection(EloquentCollection|Collection $courts, Club $club, Request $request): array
    {
        if ($courts->isEmpty()) return [];

        $anchor = $this->resolveAnchor($club, $request);
        $ids = $courts->pluck('id')->all();
        $counts = $this->countFinishedEvents($club->id, $ids);

        return $courts->map(function ($court) use ($anchor, $counts) {
            $pivot = $court->pivot;
            return [
                'id' => (int) $court->id,
                'competition_location_id' => (int) $court->id,
                'position' => (int) ($pivot->position ?? 0),
                'distance_km' => ClubHomeCourtEnricher::distanceKm(
                    $anchor['lat'], $anchor['lng'],
                    $court->latitude, $court->longitude
                ),
                'events_hosted_count' => (int) ($counts[$court->id] ?? 0),
                'location' => (new CompetitionLocationResource($court))->resolve(request()),
            ];
        })->values()->all();
    }

    private function resolveAnchor(Club $club, Request $request): array
    {
        // 1. CLB toạ độ
        $clubLat = $club->latitude !== null ? (float) $club->latitude : null;
        $clubLng = $club->longitude !== null ? (float) $club->longitude : null;
        if ($clubLat !== null && $clubLng !== null) {
            return ['lat' => $clubLat, 'lng' => $clubLng, 'source' => 'club'];
        }

        // 2. User header
        $uLat = $request->header('X-User-Lat');
        $uLng = $request->header('X-User-Lng');
        if ($uLat !== null && $uLng !== null && is_numeric($uLat) && is_numeric($uLng)) {
            return ['lat' => (float) $uLat, 'lng' => (float) $uLng, 'source' => 'user'];
        }

        return ['lat' => null, 'lng' => null, 'source' => 'none'];
    }

    /**
     * @return array<int,int>  competition_location_id => count
     */
    private function countFinishedEvents(int $clubId, array $locationIds): array
    {
        $counts = array_fill_keys($locationIds, 0);

        $mini = MiniTournament::query()
            ->where('club_id', $clubId)
            ->whereIn('competition_location_id', $locationIds)
            ->where('status', MiniTournament::STATUS_CLOSED)
            ->selectRaw('competition_location_id, COUNT(*) as cnt')
            ->groupBy('competition_location_id')
            ->pluck('cnt', 'competition_location_id');

        foreach ($mini as $locId => $cnt) {
            $counts[(int) $locId] = ($counts[(int) $locId] ?? 0) + (int) $cnt;
        }

        $tour = Tournament::query()
            ->where('club_id', $clubId)
            ->whereIn('competition_location_id', $locationIds)
            ->where('status', Tournament::CLOSED)
            ->selectRaw('competition_location_id, COUNT(*) as cnt')
            ->groupBy('competition_location_id')
            ->pluck('cnt', 'competition_location_id');

        foreach ($tour as $locId => $cnt) {
            $counts[(int) $locId] = ($counts[(int) $locId] ?? 0) + (int) $cnt;
        }

        return $counts;
    }
}
