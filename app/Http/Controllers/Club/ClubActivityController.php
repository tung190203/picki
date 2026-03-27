<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubActivityParticipantStatus;
use App\Enums\ClubMemberRole;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\CancelActivityRequest;
use App\Http\Requests\Club\GetActivitiesRequest;
use App\Http\Requests\Club\StoreActivityRequest;
use App\Http\Requests\Club\UpdateActivityRequest;
use App\Http\Resources\Club\ClubActivityListResource;
use App\Http\Resources\Club\ClubActivityParticipantResource;
use App\Http\Resources\Club\ClubActivityResource;
use App\Http\Resources\Club\ClubMixedContentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
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

        $currentVersion = (int) Cache::get('club_content_version:' . $clubId, 0);
        $cacheKey = 'club_content:' . $clubId . ':' . md5(json_encode($filters) . ':' . $category . ':' . ($userId ?? 'guest'));

        $cached = Cache::get($cacheKey);
        if ($cached !== null && ($cached['_v'] ?? 0) === $currentVersion) {
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
        $responseData['_v'] = $currentVersion;
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

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $statuses = $filters['statuses'] ?? [];
        $hasAll = in_array('all', $statuses);
        $isHistoryOnly = !empty($statuses)
            && empty(array_diff($statuses, ['completed', 'cancelled']));

        // 草稿不受周范围日期限制；且须能匹配 created_by（或服务端曾写入的主办人 staff）
        $showCreatorDrafts = (bool) $userId && ! $isHistoryOnly;

        $query->where(function ($outer) use ($dateFrom, $dateTo, $statuses, $hasAll, $userId, $showCreatorDrafts) {
            $outer->where(function ($main) use ($dateFrom, $dateTo, $statuses, $hasAll) {
                if (! empty($dateFrom)) {
                    $main->whereDate('start_time', '>=', $dateFrom);
                }
                if (! empty($dateTo)) {
                    $main->whereDate('start_time', '<=', $dateTo);
                }

                if (! $hasAll && ! empty($statuses)) {
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

                    if (! empty($mappedStatuses)) {
                        $main->whereIn('status', $mappedStatuses);
                    }
                } elseif (empty($statuses)) {
                    $main->whereIn('status', [MiniTournament::STATUS_OPEN]);
                }
            });

            if ($showCreatorDrafts) {
                $outer->orWhere(function ($draftQ) use ($userId) {
                    $draftQ->where('status', MiniTournament::STATUS_DRAFT)
                        ->where(function ($who) use ($userId) {
                            $who->where('mini_tournaments.created_by', $userId)
                                ->orWhereHas('staff', function ($sq) use ($userId) {
                                    $sq->where('users.id', $userId)
                                        ->where('mini_tournament_staff.role', MiniTournamentStaff::ROLE_ORGANIZER);
                                });
                        });
                });
            }
        });

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

            Cache::increment('club_content_version:' . $clubId);

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

            Cache::increment('club_content_version:' . $clubId);

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
            Cache::increment('club_content_version:' . $clubId);
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

            Cache::increment('club_content_version:' . $clubId);

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

            Cache::increment('club_content_version:' . $clubId);

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
            Cache::increment('club_content_version:' . $clubId);
            return ResponseHelper::success(
                ['cancelled_count' => $count],
                'Đã hủy toàn bộ chuỗi lặp lại',
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    /**
     * Admin đánh dấu member đã check-in
     */
    public function markCheckIn($clubId, $activityId, $participantId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        // Check permission: chỉ admin, manager, secretary mới được check-in hộ
        $club = $activity->club;
        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary])) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu check-in', 403);
        }

        $participant = $activity->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong hoạt động này', 404);
        }

        if ($participant->status === ClubActivityParticipantStatus::Attended) {
            return ResponseHelper::error('Thành viên đã được đánh dấu check-in rồi', 422);
        }

        if (!in_array($participant->status, [ClubActivityParticipantStatus::Accepted, ClubActivityParticipantStatus::Pending])) {
            return ResponseHelper::error('Không thể đánh dấu check-in cho trạng thái: ' . $participant->status->value, 422);
        }

        $participant->update([
            'status' => ClubActivityParticipantStatus::Attended,
            'checked_in_at' => now(),
            'is_absent' => false,
        ]);

        $participant->load('user');

        return ResponseHelper::success(
            new ClubActivityParticipantResource($participant),
            'Đã đánh dấu check-in thành công'
        );
    }
}
