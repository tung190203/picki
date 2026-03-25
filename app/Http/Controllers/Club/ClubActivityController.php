<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\CancelActivityRequest;
use App\Http\Requests\Club\GetActivitiesRequest;
use App\Http\Requests\Club\StoreActivityRequest;
use App\Http\Requests\Club\UpdateActivityRequest;
use App\Http\Resources\Club\ClubActivityListResource;
use App\Http\Resources\Club\ClubActivityResource;
use App\Http\Resources\Club\ClubMixedContentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\MiniTournament;
use App\Models\User;
use App\Services\Club\ClubActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ClubActivityController extends Controller
{
    private const ACTIVITY_COLLECTED_SUM = 'activityFeeTransactions as collected_amount';

    private const ACTIVITIES_CACHE_TTL = 60; // seconds

    public function __construct(
        protected ClubActivityService $activityService
    ) {
    }

    /**
     * Danh sách categories cho màn hoạt động
     */
    public static function getCategories(): array
    {
        return [
            ['key' => 'all', 'label' => 'Tất cả', 'icon' => null],
            ['key' => 'activity', 'label' => 'Hoạt động', 'icon' => 'calendar'],
            ['key' => 'mini_tournament', 'label' => 'Kèo', 'icon' => 'soccer'],
            ['key' => 'tournament', 'label' => 'Giải đấu', 'icon' => 'trophy'],
        ];
    }

    public function index(GetActivitiesRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();
        $filters = $request->validated();
        $category = $filters['category'] ?? 'all';

        // Client gửi statuses = tab hiện tại
        $clientSentStatuses = $request->has('statuses');
        if (!$clientSentStatuses) {
            $filters['statuses'] = ['scheduled', 'ongoing'];
        } elseif (isset($filters['statuses']) && !is_array($filters['statuses'])) {
            $filters['statuses'] = array_filter([$filters['statuses']]);
        }
        $statusesOnlyCompletedOrCancelled = !empty($filters['statuses'])
            && empty(array_diff($filters['statuses'], ['completed', 'cancelled']));

        $clientSentDate = $request->has('date_from') || $request->has('from_date') || $request->has('date_to') || $request->has('to_date');
        if (!$clientSentDate) {
            if (!$statusesOnlyCompletedOrCancelled) {
                $filters['date_from'] = Carbon::now()->startOfWeek()->format('Y-m-d');
                $filters['date_to'] = Carbon::now()->endOfWeek()->format('Y-m-d');
                $filters['include_next_occurrence_for_series_done_this_week'] = true;
            }
        } else {
            if (empty($filters['date_from']) && $request->has('from_date')) {
                $filters['date_from'] = $request->input('from_date');
            }
            if (empty($filters['date_to']) && $request->has('to_date')) {
                $filters['date_to'] = $request->input('to_date');
            }
        }

        $cacheKey = 'club_content:' . $clubId . ':' . md5(json_encode($filters) . ':' . $category . ':' . ($userId ?? 'guest'));

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $options = config('app.debug') ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : JSON_UNESCAPED_UNICODE;
            return response()->json($cached, 200, [], $options);
        }

        $categories = self::getCategories();
        $items = collect();
        $totalCount = 0;

        // Query based on category (default: all)
        $isHistoryOnly = $statusesOnlyCompletedOrCancelled;
        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';

        // Activities
        if ($category === 'all' || $category === 'activity') {
            $activities = $this->activityService->getActivities($club, $filters, $userId);
            foreach ($activities->items() as $activity) {
                $items->push([
                    'id' => $activity->id,
                    'type' => 'activity',
                    'data' => new ClubActivityListResource($activity),
                ]);
            }
            $totalCount += $activities->total();
        }

        // Mini Tournaments
        if ($category === 'all' || $category === 'mini_tournament') {
            $miniTournaments = $this->getMiniTournaments($club, $filters, $userId);
            foreach ($miniTournaments as $tournament) {
                $items->push([
                    'id' => $tournament->id,
                    'type' => 'mini_tournament',
                    'data' => new \App\Http\Resources\ListMiniTournamentResource($tournament),
                ]);
            }
            $totalCount += $miniTournaments->count();
        }

        // Sort by start_time
        $items = $isHistoryOnly
            ? $items->sortByDesc(fn($i) => $i['data']->resource->start_time ?? '')->values()
            : $items->sortBy(fn($i) => $i['data']->resource->start_time ?? '')->values();

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $currentPage = $filters['page'] ?? 1;
        $offset = ($currentPage - 1) * $perPage;
        $paginatedItems = $items->slice($offset, $perPage)->values();

        $data = [
            'categories' => $categories,
            'items' => $paginatedItems,
        ];

        $meta = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $totalCount,
            'last_page' => (int) ceil($totalCount / $perPage),
        ];

        $response = ResponseHelper::success($data, 'Lấy danh sách nội dung thành công', 200, $meta);
        $responseData = $response->getData(true);
        Cache::put($cacheKey, $responseData, self::ACTIVITIES_CACHE_TTL);

        return $response;
    }

    /**
     * Lấy danh sách mini tournaments của club
     */
    private function getMiniTournaments(Club $club, array $filters, ?int $userId)
    {
        $query = MiniTournament::withFullRelations()
            ->where('club_id', $club->id);

        // Apply date filters
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if (!empty($dateFrom)) {
            $query->whereDate('start_time', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('start_time', '<=', $dateTo);
        }

        // Apply status filters
        $statuses = $filters['statuses'] ?? [];
        $hasAll = in_array('all', $statuses);
        $isHistoryOnly = !empty($statuses)
            && empty(array_diff($statuses, ['completed', 'cancelled']));

        if (!$hasAll && !empty($statuses)) {
            $mappedStatuses = [];
            foreach ($statuses as $status) {
                $mappedStatuses[] = match ($status) {
                    'scheduled', 'ongoing' => MiniTournament::STATUS_OPEN,
                    'completed' => MiniTournament::STATUS_CLOSED,
                    'cancelled' => MiniTournament::STATUS_CANCELLED,
                    default => null,
                };
            }
            $mappedStatuses = array_filter($mappedStatuses);

            if (!empty($mappedStatuses)) {
                $query->whereIn('status', $mappedStatuses);
            }
        } elseif (empty($statuses)) {
            // Default: show ongoing/upcoming
            $query->whereIn('status', [MiniTournament::STATUS_OPEN]);
        }

        // Sort
        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';
        $query->orderBy('start_time', $orderDirection);

        return $query->limit($filters['per_page'] ?? 50)->get();
    }

    public function store(StoreActivityRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $activity = $this->activityService->createActivity($club, $request->validated(), $userId);

            Cache::increment('club_activities_version:' . $clubId);

            $activity->load([
                'creator' => User::FULL_RELATIONS,
                'participants.user' => User::FULL_RELATIONS
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Tạo hoạt động thành công', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function show($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)
            ->with([
                'creator' => User::FULL_RELATIONS,
                'club',
                'participants',
                'participants.user' => User::FULL_RELATIONS,
                'participants.walletTransaction',
                'miniTournament',
                'fundCollection.contributions.user' => User::FULL_RELATIONS,
                'fundCollection.assignedMembers' => User::FULL_RELATIONS,
            ])
            ->withSum(self::ACTIVITY_COLLECTED_SUM, 'amount')
            ->findOrFail($activityId);

        return ResponseHelper::success(new ClubActivityResource($activity), 'Lấy thông tin hoạt động thành công');
    }

    public function update(UpdateActivityRequest $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $activity = $this->activityService->updateActivity($activity, $request->validated(), $userId);

            Cache::increment('club_activities_version:' . $clubId);

            $activity->load([
                'creator' => User::FULL_RELATIONS,
                'participants.user' => User::FULL_RELATIONS
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Cập nhật hoạt động thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function destroy($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->activityService->deleteActivity($activity, $userId);
            Cache::increment('club_activities_version:' . $clubId);
            return ResponseHelper::success('Xóa hoạt động thành công');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'scheduled') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function complete($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $activity = $this->activityService->completeActivity($activity, $userId);

            Cache::increment('club_activities_version:' . $clubId);

            $activity->load([
                'creator' => User::FULL_RELATIONS,
                'participants.user' => User::FULL_RELATIONS
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Hoạt động đã được đánh dấu hoàn thành');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function cancel(CancelActivityRequest $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $activity = $this->activityService->cancelActivity(
                $activity,
                $userId,
                $request->input('cancellation_reason'),
                $request->input('cancel_transactions')
            );

            Cache::increment('club_activities_version:' . $clubId);

            $activity->load([
                'creator' => User::FULL_RELATIONS,
                'participants.user' => User::FULL_RELATIONS
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Sự kiện đã được hủy');
        } catch (\Exception $e) {
            $statusCode = 403;
            if (str_contains($e->getMessage(), 'scheduled')) {
                $statusCode = 422;
            } elseif (str_contains($e->getMessage(), 'ví chính')) {
                $statusCode = 404;
            }
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function cancelRecurrenceSeries(\Illuminate\Http\Request $request, $clubId, $activityId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $count = $this->activityService->cancelRecurrenceSeries($club, (string) $activityId, $userId);
            Cache::increment('club_activities_version:' . $clubId);
            return ResponseHelper::success(
                ['cancelled_count' => $count],
                'Đã hủy toàn bộ chuỗi lặp lại',
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }
}
