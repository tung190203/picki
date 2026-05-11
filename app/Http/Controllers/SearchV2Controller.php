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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchV2Controller extends Controller
{
    public function __construct(
        protected SearchV2Service $searchService,
        protected SearchCacheService $cacheService
    ) {}

    /**
     * Unified search endpoint.
     * GET /api/search/v2?tab=match&keyword=&time_filter=this_week&...
     *
     * Also called by aliases:
     * - GET /api/matches/search  (tab=match)
     * - GET /api/clubs/search    (tab=club)
     * - GET /api/players/search  (tab=user)
     * - GET /api/courts/search   (tab=court)
     */
    public function search(SearchRequest $request)
    {
        $params = $request->validatedWithDefaults();
        $tab = $params['tab'];
        $timeFilter = $params['time_filter'];

        $userId = Auth::check() ? Auth::id() : null;
        $isMap = filter_var($params['map_mode'], FILTER_VALIDATE_BOOLEAN);
        $filters = $params['filters'] ?? [];

        $query = $this->buildQuery($tab, $params, $filters, $timeFilter, $userId);

        if ($isMap) {
            return $this->mapResponse($query, $tab);
        }

        if ($timeFilter === 'this_week') {
            return $this->timelineWeekResponse($query, $tab, $params);
        }

        $result = $this->paginate($query, $params);
        $this->logSearch($userId, $tab, $params['keyword'] ?? null, $filters, $timeFilter, $result['meta']['total'] ?? 0);

        return ResponseHelper::success([
            'data' => $result['data'],
            'meta' => $result['meta'],
        ], 'Tìm kiếm thành công', 200);
    }

    /**
     * Returns filter configuration for a given tab.
     * GET /api/search/filters?tab=match
     */
    public function availableFilters(Request $request)
    {
        $tab = $request->input('tab', 'match');

        if (!in_array($tab, SearchFilterConfig::getTabs(), true)) {
            return ResponseHelper::error('Tab không hợp lệ.', 400);
        }

        return ResponseHelper::success([
            'tabs'     => SearchFilterConfig::availableTabs(),
            'config'   => SearchFilterConfig::getConfig($tab),
            'hot_searches' => $this->cacheService->getHotSearches($tab),
        ], 'Lấy cấu hình filter thành công', 200);
    }

    /**
     * Returns popular/quick searches.
     * GET /api/search/quick
     */
    public function quick(Request $request)
    {
        $tab = $request->input('tab', 'match');

        return ResponseHelper::success([
            'hot_searches' => $this->cacheService->getHotSearches($tab, 8),
        ], 'Lấy tìm kiếm nhanh thành công', 200);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildQuery(string $tab, array $params, array $filters, string $timeFilter, ?int $userId)
    {
        $user = Auth::user();
        $userId = $userId ?? ($user ? $user->id : null);

        $query = match ($tab) {
            SearchFilterConfig::TAB_MATCH => MiniTournament::withFullRelations()
                ->whereDate('start_time', '>=', now()->toDateString())
                ->filter($filters),

            SearchFilterConfig::TAB_TOURNAMENT => Tournament::withFullRelations()
                ->whereDate('start_date', '>=', now()->toDateString())
                ->filter($filters),

            SearchFilterConfig::TAB_USER => User::query()
                ->with(['sports.sport', 'clubs'])
                ->when($userId, fn($q) => $q->where('id', '!=', $userId))
                ->when($user, fn($q) => $q->visibleFor($user))
                ->filter($filters)
                ->applyTimeline($timeFilter, $userId),

            SearchFilterConfig::TAB_CLUB => Club::withListRelations()
                ->filter($filters),

            SearchFilterConfig::TAB_COURT => CompetitionLocation::withFullRelations()
                ->filter($filters),

            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };

        // Timeline filter (except user which has its own logic in the query above)
        if ($tab !== SearchFilterConfig::TAB_USER && $tab !== SearchFilterConfig::TAB_COURT) {
            $query = $query->applyTimeline($timeFilter, $userId);
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
            if ($tab === SearchFilterConfig::TAB_USER || $tab === SearchFilterConfig::TAB_CLUB) {
                $query->orderByDistance($lat, $lng);
            } else {
                $query->orderByDistanceFromLocation($lat, $lng);
            }
        }

        if ($lat !== null && $lng !== null && $radius !== null) {
            $query->nearBy($lat, $lng, $radius);
        }

        $hasFilter = !empty($params['keyword']) || !empty($params['sport_id']) ||
                     !empty($params['location_id']) || !empty($params['filters'] ?? []);
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
        $resourceClass = $this->searchService->resolveListResourceClass($tab);
        $dateField = $this->searchService->resolveDateField($tab);

        $items = $query->get();
        $groups = $this->searchService->buildWeekdayGroups($items, $dateField);

        $data = collect($groups)->map(fn($group) => [
            'day'      => $group['day'],
            'is_today' => $group['is_today'],
            'count'    => $group['count'],
            'items'    => $resourceClass::collect($group['items']),
        ])->filter(fn($group) => $group['count'] > 0)->values();

        $this->logSearch(
            Auth::check() ? Auth::id() : null,
            $tab,
            $params['keyword'] ?? null,
            $params['filters'] ?? [],
            $params['time_filter'] ?? 'all',
            $items->count()
        );

        return ResponseHelper::success([
            'data'     => $data,
            'timeline' => true,
            'meta'     => [
                'total'        => $items->count(),
                'time_filter'  => 'this_week',
            ],
        ], 'Tìm kiếm theo tuần thành công', 200);
    }

    private function logSearch(?int $userId, string $tab, ?string $keyword, ?array $filters, ?string $timeFilter, int $resultCount): void
    {
        try {
            $this->cacheService->logSearch($userId, $tab, $keyword, $filters, $timeFilter, $resultCount);
        } catch (\Throwable) {
            // Don't fail the search request if logging fails
        }
    }
}
