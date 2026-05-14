<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\Map\MapClubResource;
use App\Http\Resources\Map\MapCourtResource;
use App\Http\Resources\Map\MapMiniTournamentResource;
use App\Http\Resources\Map\MapTournamentResource;
use App\Http\Resources\Map\MapUserResource;
use App\Models\Club\Club;
use App\Models\CompetitionLocation;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use App\Services\SearchCacheService;
use App\Services\SearchFilterConfig;
use App\Services\SearchV2Service;
use Illuminate\Support\Facades\Auth;

class SearchV2Controller extends Controller
{
    public function __construct(
        protected SearchV2Service $searchService,
        protected SearchCacheService $cacheService
    ) {}

    /**
     * Unified search endpoint.
     * GET /api/search/?tab=mini-tournament&keyword=&sub_tab=...
     *
     * Alias routes (same handler, different default tab via route defaults):
     * - GET /api/matches/search  (tab=mini-tournament)
     * - GET /api/clubs/search    (tab=club)
     * - GET /api/players/search  (tab=user)
     * - GET /api/courts/search   (tab=court)
     */
    public function search(SearchRequest $request)
    {
        $params = $request->validatedWithDefaults();
        $tab = $params['tab'];
        $subTab = $params['sub_tab'];

        $userId = Auth::check() ? Auth::id() : null;
        $isMap = filter_var($params['map_mode'], FILTER_VALIDATE_BOOLEAN);

        // Inject location filters into filters array
        $filters = $params['filters'] ?? [];
        if (!empty($params['location_id'])) {
            $filters['location_id'] = (int) $params['location_id'];
        }
        if (!empty($params['competition_location_id'])) {
            $filters['competition_location_id'] = (int) $params['competition_location_id'];
        }

        $query = $this->buildQuery($tab, $params, $filters, $subTab, $userId);

        if ($isMap) {
            return $this->mapResponse($query, $tab);
        }

        if ($subTab === 'this_week') {
            return $this->timelineWeekResponse($query, $tab, $params);
        }

        $result = $this->paginate($query, $params);
        $this->logSearch($userId, $tab, $params['keyword'] ?? null, $filters, $subTab, $result['meta']['total'] ?? 0);

        return ResponseHelper::success([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], 'Tìm kiếm thành công', 200);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildQuery(string $tab, array $params, array $filters, string $subTab, ?int $userId)
    {
        $user = Auth::user();
        $userId = $userId ?? ($user ? $user->id : null);

        $query = match ($tab) {
            SearchFilterConfig::TAB_MATCH => MiniTournament::withFullRelations()
                ->whereDate('start_time', '>=', now()->toDateString())
                ->filter($filters),

            SearchFilterConfig::TAB_TOURNAMENT => Tournament::withFullRelations()
                ->with(['tournamentStaffs', 'participants'])
                ->whereDate('start_date', '>=', now()->toDateString())
                ->filter($filters),

            SearchFilterConfig::TAB_USER => User::query()
                ->with(['sports.sport', 'sports.scores', 'clubs'])
                ->when($userId, fn($q) => $q->where('id', '!=', $userId))
                ->when($user, fn($q) => $q->visibleFor($user))
                ->filter($filters)
                ->applyTimeline($subTab, $userId),

            SearchFilterConfig::TAB_CLUB => Club::withListRelations()
                ->filter($filters),

            SearchFilterConfig::TAB_COURT => CompetitionLocation::withFullRelations()
                ->filter($filters),

            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };

        // Sub-tab filter (except user which has its own logic in the query above)
        if ($tab !== SearchFilterConfig::TAB_USER && $tab !== SearchFilterConfig::TAB_COURT) {
            $query = $query->applyTimeline($subTab, $userId);
        }

        // Geo filters
        $this->applyGeoFilters($query, $params, $tab);

        return $query;
    }

    private function applyGeoFilters($query, array $params, string $tab): void
    {
        $lat = $params['lat'] ?? null;
        $lng = $params['lng'] ?? null;
        $radius = $params['radius'] ?? null;

        if ($lat !== null && $lng !== null) {
            if ($tab === SearchFilterConfig::TAB_USER || $tab === SearchFilterConfig::TAB_CLUB || $tab === SearchFilterConfig::TAB_COURT) {
                $query->orderByDistance($lat, $lng);
            } else {
                $query->orderByDistanceFromLocation($lat, $lng);
            }
        }

        if ($lat !== null && $lng !== null && $radius !== null) {
            $query->nearBy($lat, $lng, $radius);
        }

        $hasFilter = !empty($params['keyword']) || !empty($params['sport_id']) ||
                     !empty($params['location_id']) || !empty($params['competition_location_id']) || !empty($params['filters'] ?? []);
        $hasBounds = !empty($params['minLat']) || !empty($params['maxLat']) ||
                     !empty($params['minLng']) || !empty($params['maxLng']);

        if (!$hasFilter && $hasBounds) {
            $query->inBounds(
                $params['minLat'] ?? null,
                $params['maxLat'] ?? null,
                $params['minLng'] ?? null,
                $params['maxLng'] ?? null,
            );
        }
    }

    private function paginate($query, array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $perPage = min(200, max(1, (int) ($params['per_page'] ?? 15)));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $resourceClass = $this->searchService->resolveListResourceClass($params['tab']);

        return [
            'data' => $resourceClass::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    private function mapResponse($query, string $tab): \Illuminate\Http\JsonResponse
    {
        $resourceClass = $this->searchService->resolveResourceClass($tab);
        $items = $query->get();
        $bounds = $this->searchService->computeBounds($items, $tab);

        return ResponseHelper::success([
            'data'   => $resourceClass::collection($items),
            'bounds' => $bounds,
            'meta'   => [
                'total'    => $items->count(),
                'map_mode' => true,
            ],
        ], 'Tìm kiếm bản đồ thành công', 200);
    }

    private function timelineWeekResponse($query, string $tab, array $params): \Illuminate\Http\JsonResponse
    {
        $page = (int) ($params['page'] ?? 1);
        $perPage = min(200, max(1, (int) ($params['per_page'] ?? 15)));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $resourceClass = $this->searchService->resolveListResourceClass($tab);

        $this->logSearch(
            Auth::check() ? Auth::id() : null,
            $tab,
            $params['keyword'] ?? null,
            $params['filters'] ?? [],
            'this_week',
            $paginator->total()
        );

        return ResponseHelper::success([
            'data' => $resourceClass::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ], 'Tìm kiếm thành công', 200);
    }

    private function logSearch(?int $userId, string $tab, ?string $keyword, ?array $filters, ?string $subTab, int $resultCount): void
    {
        try {
            $this->cacheService->logSearch($userId, $tab, $keyword, $filters, $subTab, $resultCount);
        } catch (\Throwable) {
            // Don't fail the search request if logging fails
        }
    }
}
