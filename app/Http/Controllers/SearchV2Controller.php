<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\Map\MapClubResource;
use App\Http\Resources\Map\MapCourtResource;
use App\Http\Resources\Map\MapMiniTournamentResource;
use App\Http\Resources\Map\MapTournamentResource;
use App\Http\Resources\Map\MapUserResource;
use App\Http\Resources\Search\SuggestClubResource;
use App\Models\Club\Club;
use App\Models\CompetitionLocation;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Club\ClubSearchEnricher;
use App\Services\Club\ClubSuggestService;
use App\Services\Search\SearchOrganizerEnricher;
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

        // sub_tab=suggest: dedicated 3-bucket suggestion (friend_in_club → following, suit_level, nearby).
        // List-only — never returned in map mode. Client per_page is intentionally ignored: the service
        // caps results at TOTAL_LIMIT (30) and groups already break the list into sections.
        if ($tab === SearchFilterConfig::TAB_CLUB && $subTab === 'suggest') {
            return $this->suggestResponse($userId, $params);
        }

        if ($isMap) {
            return $this->mapResponse($query, $tab, $params);
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
                // same_club bỏ qua visibleFor: ai trong club đó đều thấy nhau bất kể visibility (open/friend-only/private).
                // whereHas('clubs', $clubId) phía dưới đã tự giới hạn về thành viên club.
                ->when($user && $subTab !== 'same_club', fn($q) => $q->visibleFor($user))
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

            SearchFilterConfig::TAB_CLUB => $this->buildClubQuery($userId, $filters, $subTab),

            SearchFilterConfig::TAB_COURT => CompetitionLocation::withFullRelations()
                ->active()
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
        $filters = $params['filters'] ?? [];

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

        // Handle distance filter: [min_km, max_km] range for TAB_COURT
        // Must be applied after orderByDistance() which selects the distance column
        if ($tab === SearchFilterConfig::TAB_COURT
            && !empty($filters['distance'])
            && is_array($filters['distance'])
        ) {
            $query->having('distance', '>=', $filters['distance'][0]);
            if (isset($filters['distance'][1])) {
                $query->having('distance', '<=', $filters['distance'][1]);
            }
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

    private function buildClubQuery(?int $userId, array $filters, string $subTab = 'all')
    {
        $isSuperAdmin = $userId && \App\Models\User::isSuperAdmin($userId);

        $query = Club::withSearchRelations($userId)
            ->with(['creator', 'members'])
            ->when(!$isSuperAdmin, fn($q) => $q->where('status', '!=', \App\Enums\ClubStatus::Suspended))
            ->where(function ($q) use ($userId, $isSuperAdmin, $subTab) {
                $q->where('is_public', true);

                if ($userId && $subTab !== 'following') {
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

        // following sub-tab: chỉ trả clubs user đang follow
        if ($subTab === 'following') {
            if (!$userId) {
                // Chưa login → trả empty
                $query->whereRaw('1 = 0');
            } else {
                $query->following($userId);
            }
        }

        // suit_level sub-tab: chỉ trả clubs có score range phù hợp với user score (±0.5)
        if ($subTab === 'suit_level') {
            if (!$userId) {
                $query->whereRaw('1 = 0');
            } else {
                $userScore = $this->getUserVnduprScore($userId);
                if ($userScore === null) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->suitLevel($userScore, 0.5);
                    // Lưu user score để enricher sử dụng cho sort
                    request()->attributes->set('suit_level_user_score', $userScore);
                    request()->attributes->set('suit_level_tolerance', 0.5);
                }
            }
        }

        return $query;
    }

    /**
     * Lấy vndupr_score cao nhất của user cho sport_id (default = 1 = Pickleball).
     * Trả về null nếu user chưa có score.
     */
    private function getUserVnduprScore(int $userId): ?float
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return null;
        }
        $score = $user->vnduprScoresBySport(\App\Models\Sport::PICKLEBALL_ID)->max('score_value');
        return $score !== null ? (float) $score : null;
    }

    private function paginate($query, array $params): array
    {
        $tab = $params['tab'];
        $userId = Auth::check() ? Auth::id() : null;

        // If client didn't pass per_page, return ALL results (no pagination).
        // Clients can opt into pagination by sending per_page + page.
        if (empty($params['per_page'])) {
            $items = $query->get();

            if ($tab === SearchFilterConfig::TAB_USER) {
                User::loadSportStatsOnUsers($items, 1);
            }
            $this->loadBatchMembershipStatus($items, $tab, $userId);
            $this->enrichClubResults($items, $tab, $userId);

            $resourceClass = $this->searchService->resolveListResourceClass($tab);

            return [
                'data' => $this->renderItems($items, $tab, $resourceClass, $params),
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $items->count(),
                    'total'        => $items->count(),
                ],
            ];
        }

        $page = (int) ($params['page'] ?? 1);
        $perPage = min(200, max(1, (int) $params['per_page']));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Eager-load batch stats for user tab to avoid N+1 (getSportStats is expensive)
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($paginator->getCollection(), 1);
        }

        // Eager-load batch membership status for tournament tabs (avoids N+1 on isJoinedBy/isRegisteredBy)
        $this->loadBatchMembershipStatus($paginator->getCollection(), $tab, $userId);
        $this->enrichClubResults($paginator->getCollection(), $tab, $userId);

        $resourceClass = $this->searchService->resolveListResourceClass($params['tab']);

        return [
            'data' => $this->renderItems($paginator->getCollection(), $tab, $resourceClass, $params),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    private function suggestResponse(?int $userId, array $params): \Illuminate\Http\JsonResponse
    {
        $lat = $params['lat'] ?? null;
        $lng = $params['lng'] ?? null;

        /** @var ClubSuggestService $service */
        $service = app(ClubSuggestService::class);

        // Anonymous: nothing to suggest. (FE only enters this sub-tab when logged in, but be safe.)
        if (!$userId) {
            $items = collect();
        } else {
            $items = $service->suggest($userId, $lat !== null ? (float) $lat : null, $lng !== null ? (float) $lng : null);
            // ClubSuggestService does not eager-load creator/members (3 separate rank queries);
            // SearchClubResource needs both to render admin + membership flags + vndupr_score
            // (hasManyThrough via user_sport_scores). Load here at the boundary — single batch.
            $items->load('creator.vnduprScores', 'members.user.vnduprScores');
        }

        $data = SuggestClubResource::collection($items)->toArray(request());

        $this->logSearch($userId, SearchFilterConfig::TAB_CLUB, $params['keyword'] ?? null, [], 'suggest', count($data));

        return ResponseHelper::success([
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => count($data),
                'total'        => count($data),
            ],
        ], 'Tìm kiếm thành công', 200);
    }

    private function mapResponse($query, string $tab, array $params): \Illuminate\Http\JsonResponse
    {
        // map_mode always returns ALL matching items (per_page is intentionally ignored)
        $items = $query->get();
        $userId = Auth::check() ? Auth::id() : null;

        // Eager-load batch stats for user tab to avoid N+1
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($items, 1);
        }

        // Eager-load batch membership status for tournament tabs
        $this->loadBatchMembershipStatus($items, $tab, $userId);
        $this->enrichClubResults($items, $tab, $userId);

        $bounds = $this->searchService->computeBounds($items, $tab);
        // Use list resource (has sports field) for user tab, map resource for others
        $resourceClass = $tab === SearchFilterConfig::TAB_USER
            ? $this->searchService->resolveListResourceClass($tab)
            : $this->searchService->resolveResourceClass($tab);

        return ResponseHelper::success([
            'data'   => $this->renderItems($items, $tab, $resourceClass, $params),
            'bounds' => $bounds,
            'meta'   => [
                'total'    => $items->count(),
                'map_mode' => true,
            ],
        ], 'Tìm kiếm bản đồ thành công', 200);
    }

    private function timelineWeekResponse($query, string $tab, array $params): \Illuminate\Http\JsonResponse
    {
        $userId = Auth::check() ? Auth::id() : null;

        // If client didn't pass per_page, return ALL results for the week.
        if (empty($params['per_page'])) {
            $items = $query->get();

            if ($tab === SearchFilterConfig::TAB_USER) {
                User::loadSportStatsOnUsers($items, 1);
            }
            $this->loadBatchMembershipStatus($items, $tab, $userId);
            $this->enrichClubResults($items, $tab, $userId);

            $resourceClass = $this->searchService->resolveListResourceClass($tab);

            $this->logSearch(
                $userId,
                $tab,
                $params['keyword'] ?? null,
                $params['filters'] ?? [],
                'this_week',
                $items->count()
            );

            return ResponseHelper::success([
                'data' => $this->renderItems($items, $tab, $resourceClass, $params),
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $items->count(),
                    'total'        => $items->count(),
                ],
            ], 'Tìm kiếm thành công', 200);
        }

        $page = (int) ($params['page'] ?? 1);
        $perPage = min(200, max(1, (int) $params['per_page']));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Eager-load batch stats for user tab to avoid N+1
        if ($tab === SearchFilterConfig::TAB_USER) {
            User::loadSportStatsOnUsers($paginator->getCollection(), 1);
        }

        // Eager-load batch membership status for tournament tabs
        $this->loadBatchMembershipStatus($paginator->getCollection(), $tab, $userId);
        $this->enrichClubResults($paginator->getCollection(), $tab, $userId);

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
            'data' => $this->renderItems($paginator->getCollection(), $tab, $resourceClass, $params),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ], 'Tìm kiếm thành công', 200);
    }

    /**
     * Render a collection of items, appending club virtual members for tab=user sub_tab=same_club.
     * Centralizes the virtual-member rendering so every entry path (paginate, map, timeline-week)
     * applies the same rule. Real users go through the resource class; virtual members are
     * transformed inline so their shape stays compatible with what the FE invite flow expects.
     */
    private function renderItems($items, string $tab, string $resourceClass, array $params): array
    {
        if ($tab !== SearchFilterConfig::TAB_USER
            || ($params['sub_tab'] ?? null) !== 'same_club'
            || empty($params['club_id'])
        ) {
            return $resourceClass::collection($items)->toArray(request());
        }

        // Virtual members only get appended to the first page (default or explicit page=1)
        // so they don't get duplicated across paginated loads.
        $page = (int) ($params['page'] ?? 1);
        if ($page !== 1) {
            return $resourceClass::collection($items)->toArray(request());
        }

        $virtualArrays = $this->buildVirtualMemberArrays((int) $params['club_id'], $params['keyword'] ?? null);

        return array_merge(
            $resourceClass::collection($items)->toArray(request()),
            $virtualArrays,
        );
    }

    /**
     * Build JSON-shaped arrays for ClubGuestProfile records of a given club.
     * Shape mirrors SearchPlayerResource so the FE search invite UI shows them uniformly.
     * Marker `is_guest: true` + `club_guest_profile_id` lets downstream invite
     * endpoints route through the CLB-guest code path.
     */
    private function buildVirtualMemberArrays(int $clubId, ?string $keyword): array
    {
        $query = \App\Models\Club\ClubGuestProfile::with('user')
            ->where('club_id', $clubId)
            ->whereHas('user');
        if (!empty($keyword)) {
            $query->whereHas('user', fn ($q) => $q->where('full_name', 'like', '%' . $keyword . '%'));
        }
        $profiles = $query->orderBy('created_at', 'desc')->get();

        $out = [];
        foreach ($profiles as $profile) {
            $user = $profile->user;
            if (!$user) {
                continue;
            }
            $out[] = [
                'id'           => $user->id, // unified: id = user.id (CLB guest giờ là User thật)
                'user_id'      => $user->id,
                'full_name'    => $user->full_name,
                'name'         => $user->full_name,
                'avatar_url'   => $user->avatar_url,
                'gender'       => null,
                'gender_text'  => null,
                'age_group'    => null,
                'visibility'   => null,
                'address'      => null,
                'is_online'    => false,
                'primary_badge' => null,
                'vn_rank'      => null,
                'vndupr_score' => null,
                'win_rate'     => 0.0,
                'total_matches' => 0,
                'distance'     => null,
                'latitude'     => null,
                'longitude'    => null,
                'sports'       => [],
                'clubs'        => [],
                'is_follow'    => false,
                'marker_type'  => 'user',
                'is_guest'     => true,
                'club_guest_profile_id' => $profile->id, // dùng cho nhánh invite CLB guest
            ];
        }
        return $out;
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
    private function enrichClubResults($items, string $tab, ?int $userId): void
    {
        if ($items->isEmpty()) {
            return;
        }

        // Phase 4: Organizer identity cho Tournament/MiniTournament
        if ($tab === SearchFilterConfig::TAB_MATCH || $tab === SearchFilterConfig::TAB_TOURNAMENT) {
            $this->enrichTournamentResults($items);
            return;
        }

        if ($tab !== SearchFilterConfig::TAB_CLUB) {
            return;
        }

        $suitLevelUserScore = request()->attributes->get('suit_level_user_score');

        // Lấy underlying collection (hỗ trợ cả Eloquent Collection và Paginator->getCollection)
        $collection = $items instanceof \Illuminate\Pagination\AbstractPaginator
            ? $items->getCollection()
            : $items;

        ClubSearchEnricher::enrich($collection, $userId, $suitLevelUserScore);

        // Sort theo score_match_score ASC khi sub_tab=suit_level
        $subTab = request()->query('sub_tab');
        if ($subTab === 'suit_level' && $suitLevelUserScore !== null) {
            $sorted = ClubSearchEnricher::sortByScoreMatch($collection);
            if ($items instanceof \Illuminate\Pagination\AbstractPaginator) {
                $items->setCollection($sorted);
            } else {
                // Replace contents in-place bằng cách clear + refill để giữ reference
                $items->forget(array_keys($items->all()));
                foreach ($sorted as $k => $v) {
                    $items->put($k, $v);
                }
            }
        }
    }

    /**
     * Phase 4: Gắn organizer identity (id, name, avatar, club, vndupr_score,
     * organized_count, follower_count) cho match/tournament results.
     */
    private function enrichTournamentResults($items): void
    {
        $collection = $items instanceof \Illuminate\Pagination\AbstractPaginator
            ? $items->getCollection()
            : $items;

        SearchOrganizerEnricher::attachOrganizer($collection);
    }
}
