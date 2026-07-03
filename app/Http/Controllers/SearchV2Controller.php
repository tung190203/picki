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
        if (!empty($params['keyword'])) {
            $filters['keyword'] = $params['keyword'];
        }
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
            SearchFilterConfig::TAB_MATCH => MiniTournament::searchRelations()
                ->filter($filters),

            SearchFilterConfig::TAB_TOURNAMENT => Tournament::searchRelations()
                ->filter($filters),

            SearchFilterConfig::TAB_USER => User::query()
                ->with(['sports.sport', 'sports.scores', 'clubs'])
                ->when($userId, fn($q) => $q->withInteractionStatus($userId))
                ->when($user, fn($q) => $q->visibleFor($user))
                ->filter($filters)
                ->applyTimeline($subTab, $userId)
                ->when($subTab === 'same_club', function ($q) use ($params) {
                    $clubId = $params['club_id'] ?? null;
                    if ($clubId) {
                        $q->whereHas('clubs', fn($cq) => $cq->where('clubs.id', $clubId));
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                }),

            SearchFilterConfig::TAB_CLUB => $this->buildClubQuery($userId, $filters),

            SearchFilterConfig::TAB_COURT => CompetitionLocation::withFullRelations()
                ->filter($filters),

            default => throw new \InvalidArgumentException("Unknown tab: {$tab}"),
        };

        // Sub-tab filter & sort
        if ($tab !== SearchFilterConfig::TAB_USER && $tab !== SearchFilterConfig::TAB_COURT) {
            $query = $query->applyTimeline($subTab, $userId);

            // Only apply open/past sort for tournament tabs (TAB_MATCH, TAB_TOURNAMENT)
            // TAB_CLUB and TAB_COURT don't have end_date/end_time columns
            $isTournamentTab = $tab === SearchFilterConfig::TAB_MATCH || $tab === SearchFilterConfig::TAB_TOURNAMENT;

            if ($subTab === 'all' && $isTournamentTab) {
                $isMiniTournament = $tab === SearchFilterConfig::TAB_MATCH;
                $endColumn = $isMiniTournament ? 'end_time' : 'end_date';
                $startColumn = $isMiniTournament ? 'start_time' : 'start_date';

                // Only open tournaments
                $query = $query
                    ->where(function ($q) use ($endColumn) {
                        $q->whereRaw("COALESCE({$endColumn}, DATE_ADD(NOW(), INTERVAL 1 YEAR)) >= NOW()");
                    })
                    ->orderBy($startColumn, 'asc');
            }

            if ($subTab === 'mine' && $isTournamentTab) {
                $isMiniTournament = $tab === SearchFilterConfig::TAB_MATCH;
                $endColumn = $isMiniTournament ? 'end_time' : 'end_date';
                $startColumn = $isMiniTournament ? 'start_time' : 'start_date';

                // Open tournaments first, then past (sorted by most recent first)
                $query = $query
                    ->orderByRaw("CASE WHEN COALESCE({$endColumn}, DATE_ADD(NOW(), INTERVAL 1 YEAR)) >= NOW() THEN 0 ELSE 1 END")
                    ->orderByDesc($startColumn);
            }
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

    // -------------------------------------------------------------------------
    // Club query builder (includes private clubs the user is a member of)
    // -------------------------------------------------------------------------

    private function buildClubQuery(?int $userId, array $filters)
    {
        $isSuperAdmin = $userId && \App\Models\User::isSuperAdmin($userId);

        return Club::withListRelations()
            ->with(['creator', 'members'])
            ->when(!$isSuperAdmin, fn($q) => $q->where('status', '!=', \App\Enums\ClubStatus::Suspended))
            ->where(function ($q) use ($userId, $isSuperAdmin) {
                $q->where('is_public', true);

                if ($userId) {
                    if ($isSuperAdmin) {
                        $q->orWhere('is_public', false);
                    } else {
                        $q->orWhereHas('members', function ($m) use ($userId) {
                            $m->where('user_id', $userId)
                                ->where('membership_status', \App\Enums\ClubMembershipStatus::Joined->value);
                        });
                    }
                }
            })
            ->filter($filters);
    }

    private function paginate($query, array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $perPage = min(200, max(1, (int) ($params['per_page'] ?? 15)));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $tab = $params['tab'];
        $userId = Auth::check() ? Auth::id() : null;

        // Eager-load batch stats for user tab to avoid N+1 (getSportStats is expensive)
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($paginator->getCollection(), 1);
        }

        // Eager-load batch membership status for tournament tabs (avoids N+1 on isJoinedBy/isRegisteredBy)
        $this->loadBatchMembershipStatus($paginator->getCollection(), $tab, $userId);

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
        $items = $query->get();
        $userId = Auth::check() ? Auth::id() : null;

        // Eager-load batch stats for user tab to avoid N+1
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($items, 1);
        }

        // Eager-load batch membership status for tournament tabs
        $this->loadBatchMembershipStatus($items, $tab, $userId);

        $bounds = $this->searchService->computeBounds($items, $tab);
        // Use list resource (has sports field) for user tab, map resource for others
        $resourceClass = $tab === SearchFilterConfig::TAB_USER
            ? $this->searchService->resolveListResourceClass($tab)
            : $this->searchService->resolveResourceClass($tab);

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
        $userId = Auth::check() ? Auth::id() : null;

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Eager-load batch stats for user tab to avoid N+1
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($paginator->getCollection(), 1);
        }

        // Eager-load batch membership status for tournament tabs
        $this->loadBatchMembershipStatus($paginator->getCollection(), $tab, $userId);

        $resourceClass = $this->searchService->resolveListResourceClass($tab);

        $this->logSearch(
            $userId,
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

    /**
     * Batch-load isJoinedBy and isRegisteredBy status for all result rows.
     * Replaces per-row N+1 queries with 2 bulk queries per tab.
     */
    private function loadBatchMembershipStatus($items, string $tab, ?int $userId): void
    {
        if (!$userId || $items->isEmpty()) {
            return;
        }

        if ($tab === SearchFilterConfig::TAB_MATCH) {
            $ids = $items->pluck('id')->all();

            $joinedIds = \App\Models\MiniTournament::whereIn('id', $ids)
                ->whereHas('participants', fn($p) => $p->where('user_id', $userId)->where('is_confirmed', 1))
                ->pluck('id')
                ->flip()
                ->toArray();

            $registeredIds = \App\Models\MiniTournament::whereIn('id', $ids)
                ->whereHas('participants', fn($p) => $p->where('user_id', $userId))
                ->pluck('id')
                ->flip()
                ->toArray();

            foreach ($items as $item) {
                $item->preloaded_is_joined = isset($joinedIds[$item->id]);
                $item->preloaded_is_registered = isset($registeredIds[$item->id]);
            }
        }

        if ($tab === SearchFilterConfig::TAB_TOURNAMENT) {
            $ids = $items->pluck('id')->all();

            $joinedIds = \App\Models\Tournament::whereIn('id', $ids)
                ->whereHas('participants', fn($p) => $p->where('user_id', $userId)->where('is_confirmed', 1))
                ->pluck('id')
                ->flip()
                ->toArray();

            $registeredIds = \App\Models\Tournament::whereIn('id', $ids)
                ->whereHas('participants', fn($p) => $p->where('user_id', $userId))
                ->pluck('id')
                ->flip()
                ->toArray();

            foreach ($items as $item) {
                $item->preloaded_is_joined = isset($joinedIds[$item->id]);
                $item->preloaded_is_registered = isset($registeredIds[$item->id]);
            }
        }
    }

    /**
     * Batch-load per-(user_id, sport_id) stats for all UserSport models.
     * Assigns preloaded_sport_stats[$sportId] on each UserSport to avoid per-row getSportStats calls.
     */
}
