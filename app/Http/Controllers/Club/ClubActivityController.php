<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubActivityParticipantStatus;
use App\Enums\ClubMemberRole;
use App\Exceptions\BusinessException;
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
use App\Http\Resources\ListTournamentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Services\Club\ClubActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ClubActivityController extends Controller
{
    private const ACTIVITY_COLLECTED_SUM = 'activityFeeTransactions as collected_amount';

    // Caching disabled — see note in index()

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

        if ($club->is_banned && !\App\Models\User::isSuperAdmin(auth()->id())) {
            return ResponseHelper::error('CLB này tạm thời bị cấm truy cập', 403);
        }

        $userId = auth()->id();
        $filters = $request->validated();

        // Chuẩn hóa category để cache key nhất quán bất kể client có truyền hay không
        $category = $filters['category'] ?? 'all';
        $filters['category'] = $category;

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
        if ($clientSentDate) {
            if (empty($filters['date_from']) && $request->has('from_date')) {
                $filters['date_from'] = $request->input('from_date');
            }
            if (empty($filters['date_to']) && $request->has('to_date')) {
                $filters['date_to'] = $request->input('to_date');
            }
        }

        // NOTE: Caching disabled — ClubContentCache was causing stale data after mutations
        // (Cache::increment + forget patterns couldn't cover all filter combinations reliably).
        // Performance impact is acceptable for clubs with small-to-medium datasets.
        // Re-enable only when a more robust cache invalidation strategy is implemented.

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

        // Tournaments
        if ($category === 'all' || $category === 'tournament') {
            $tournaments = $this->getTournaments($club, $filters, $userId);
            foreach ($tournaments as $tournament) {
                $items->push([
                    'id' => $tournament->id,
                    'type' => 'tournament',
                    'data' => new \App\Http\Resources\ListTournamentResource($tournament),
                ]);
            }
            $totalCount += $tournaments->count();
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

        return ResponseHelper::success($data, 'Lấy danh sách nội dung thành công', 200, $meta);
    }

    /**
     * Lấy danh sách mini tournaments của club
     */
    private function getMiniTournaments(Club $club, array $filters, ?int $userId)
    {
        $query = MiniTournament::withFullRelations()
            ->with('fundCollection')
            ->where('club_id', $club->id);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        // Check if user is club staff (admin/manager/secretary/treasurer) — they can see ALL content in their club
        $isClubStaff = $userId && $club->activeMembers()
            ->where('user_id', $userId)
            ->whereIn('role', [
                ClubMemberRole::Admin,
                ClubMemberRole::Manager,
                ClubMemberRole::Secretary,
                ClubMemberRole::Treasurer,
            ])
            ->exists();

        $statuses = $filters['statuses'] ?? [];
        $hasAll = in_array('all', $statuses);
        $isHistoryOnly = !empty($statuses)
            && empty(array_diff($statuses, ['completed', 'cancelled']));

        // Map statuses từ client sang status constants
        $mappedStatuses = [];
        foreach ($statuses as $status) {
            $mapped = match ($status) {
                'scheduled' => [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN],
                'ongoing' => [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN],
                'completed' => MiniTournament::STATUS_CLOSED,
                'cancelled' => MiniTournament::STATUS_CANCELLED,
                default => null,
            };
            if (is_array($mapped)) {
                $mappedStatuses = array_merge($mappedStatuses, $mapped);
            } elseif ($mapped !== null) {
                $mappedStatuses[] = $mapped;
            }
        }
        $mappedStatuses = array_values(array_unique($mappedStatuses));

        // DEBUG: Log thông tin filter
        \Log::info('getMiniTournaments', [
            'club_id' => $club->id,
            'user_id' => $userId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'statuses' => $statuses,
            'mappedStatuses' => $mappedStatuses,
            'hasAll' => $hasAll,
            'isHistoryOnly' => $isHistoryOnly,
        ]);

        $normalStatuses = [
            MiniTournament::STATUS_OPEN,
            MiniTournament::STATUS_CLOSED,
            MiniTournament::STATUS_CANCELLED,
        ];

        // Filter status cho kèo thường
        $filterStatuses = (! $hasAll && ! empty($mappedStatuses))
            ? $mappedStatuses
            : $normalStatuses;

        // Điều kiện date
        $dateCondition = [];
        if (! empty($dateFrom)) {
            $dateCondition[] = ['operator' => '>=', 'value' => $dateFrom, 'column' => 'start_time'];
        }
        if (! empty($dateTo)) {
            $dateCondition[] = ['operator' => '<=', 'value' => $dateTo, 'column' => 'start_time'];
        }

        // Điều kiện end_time cho history
        // Với tab "Đã kết thúc", KHÔNG cần lọc thêm theo end_time
        // Vì status đã quyết định kèo đã kết thúc hay chưa
        $endTimeCondition = [];

        // Build query với OR groups
        $query->where(function ($q) use ($userId, $filterStatuses, $dateCondition, $isClubStaff) {
            // Nếu là club staff thì thấy tất cả (không cần logic phức tạp)
            if ($isClubStaff) {
                // Apply status filter
                if (!empty($filterStatuses)) {
                    $q->whereIn('status', $filterStatuses);
                }
                
                // Apply date filter
                foreach ($dateCondition as $cond) {
                    $q->whereDate($cond['column'], $cond['operator'], $cond['value']);
                }
                return;
            }

            // Logic cho member thường
            // Lựa chọn 1: Kèo DRAFT của chính mình (không bị date filter)
            if ($userId && in_array(MiniTournament::STATUS_DRAFT, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId) {
                    $sub->where('status', MiniTournament::STATUS_DRAFT)
                        ->where('created_by', $userId);
                });
            }

            // Lựa chọn 2: Kèo OPEN của chính mình (không bị date filter)
            if ($userId && in_array(MiniTournament::STATUS_OPEN, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId) {
                    $sub->where('status', MiniTournament::STATUS_OPEN)
                        ->where('created_by', $userId);
                });
            }

            // Lựa chọn 3: Kèo OPEN của người khác (bị date filter)
            if (in_array(MiniTournament::STATUS_OPEN, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId, $dateCondition) {
                    $sub->where('status', MiniTournament::STATUS_OPEN);
                    if ($userId) {
                        $sub->where('created_by', '!=', $userId);
                    }

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }
                });
            }

            // Lựa chọn 4: Kèo CLOSED/CANCELLED (bị date filter)
            $publicStatuses = array_intersect($filterStatuses, [
                MiniTournament::STATUS_CLOSED,
                MiniTournament::STATUS_CANCELLED
            ]);
            if (!empty($publicStatuses)) {
                $q->orWhere(function ($sub) use ($publicStatuses, $dateCondition) {
                    $sub->whereIn('status', $publicStatuses);

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }
                });
            }
        });

        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';
        $query->orderBy('start_time', $orderDirection);

        // DEBUG: Log SQL query
        \Log::info('getMiniTournaments SQL', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $results = $query->limit($filters['per_page'] ?? 50)->get();

        // DEBUG: Log số lượng kết quả
        \Log::info('getMiniTournaments results', [
            'count' => $results->count(),
            'ids' => $results->pluck('id')->toArray(),
            'statuses' => $results->pluck('status')->toArray(),
            'created_bys' => $results->pluck('created_by')->toArray(),
            'start_times' => $results->pluck('start_time')->map(fn($t) => $t?->format('Y-m-d H:i:s'))->toArray(),
        ]);

        return $results;
    }

    /**
     * Lấy danh sách tournaments của club
     */
    private function getTournaments(Club $club, array $filters, ?int $userId)
    {
        $query = Tournament::withFullRelations()->where('club_id', $club->id);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        \Log::info('getTournaments', [
            'club_id' => $club->id,
            'user_id' => $userId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filters' => $filters,
        ]);

        // Check if user is club staff (admin/manager/secretary/treasurer) — they can see ALL content in their club
        $isClubStaff = $userId && $club->activeMembers()
            ->where('user_id', $userId)
            ->whereIn('role', [
                ClubMemberRole::Admin,
                ClubMemberRole::Manager,
                ClubMemberRole::Secretary,
                ClubMemberRole::Treasurer,
            ])
            ->exists();

        $statuses = $filters['statuses'] ?? [];
        $hasAll = in_array('all', $statuses);
        $isHistoryOnly = !empty($statuses)
            && empty(array_diff($statuses, ['completed', 'cancelled']));

        // Map statuses từ client sang status constants
        $mappedStatuses = [];
        foreach ($statuses as $status) {
            $mapped = match ($status) {
                'scheduled' => [Tournament::DRAFT, Tournament::OPEN],
                'ongoing' => [Tournament::DRAFT, Tournament::OPEN],
                'completed' => Tournament::CLOSED,
                'cancelled' => Tournament::CANCELLED,
                default => null,
            };
            if (is_array($mapped)) {
                $mappedStatuses = array_merge($mappedStatuses, $mapped);
            } elseif ($mapped !== null) {
                $mappedStatuses[] = $mapped;
            }
        }
        $mappedStatuses = array_values(array_unique($mappedStatuses));

        $normalStatuses = [
            Tournament::OPEN,
            Tournament::CLOSED,
            Tournament::CANCELLED,
        ];

        // Filter status cho giải thường
        $filterStatuses = (! $hasAll && ! empty($mappedStatuses))
            ? $mappedStatuses
            : $normalStatuses;

        // Điều kiện date
        $dateCondition = [];
        if (! empty($dateFrom)) {
            $dateCondition[] = ['operator' => '>=', 'value' => $dateFrom, 'column' => 'start_date'];
        }
        if (! empty($dateTo)) {
            $dateCondition[] = ['operator' => '<=', 'value' => $dateTo, 'column' => 'start_date'];
        }

        // Điều kiện end_date cho history
        // Với tab "Đã kết thúc", KHÔNG cần lọc thêm theo end_date
        // Vì status đã quyết định giải đã kết thúc hay chưa
        $endDateCondition = [];

        // Build query với OR groups
        $query->where(function ($q) use ($userId, $filterStatuses, $dateCondition, $isClubStaff) {
            // Nếu là club staff thì thấy tất cả (không cần logic phức tạp)
            if ($isClubStaff) {
                // Apply status filter
                if (!empty($filterStatuses)) {
                    $q->whereIn('status', $filterStatuses);
                }
                
                // Apply date filter
                foreach ($dateCondition as $cond) {
                    $q->whereDate($cond['column'], $cond['operator'], $cond['value']);
                }
                return;
            }

            // Logic cho member thường
            // Lựa chọn 1: Giải DRAFT của chính mình (không bị date filter)
            if ($userId && in_array(Tournament::DRAFT, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId) {
                    $sub->where('status', Tournament::DRAFT)
                        ->where('created_by', $userId);
                });
            }

            // Lựa chọn 2: Giải OPEN của chính mình (không bị date filter)
            if ($userId && in_array(Tournament::OPEN, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId) {
                    $sub->where('status', Tournament::OPEN)
                        ->where('created_by', $userId);
                });
            }

            // Lựa chọn 3: Giải OPEN của người khác (bị date filter)
            if (in_array(Tournament::OPEN, $filterStatuses)) {
                $q->orWhere(function ($sub) use ($userId, $dateCondition) {
                    $sub->where('status', Tournament::OPEN);
                    if ($userId) {
                        $sub->where('created_by', '!=', $userId);
                    }

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }
                });
            }

            // Lựa chọn 4: Giải CLOSED/CANCELLED (bị date filter)
            $publicStatuses = array_intersect($filterStatuses, [
                Tournament::CLOSED,
                Tournament::CANCELLED
            ]);
            if (!empty($publicStatuses)) {
                $q->orWhere(function ($sub) use ($publicStatuses, $dateCondition) {
                    $sub->whereIn('status', $publicStatuses);

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }
                });
            }
        });

        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';
        $query->orderBy('start_date', $orderDirection);

        $results = $query->limit($filters['per_page'] ?? 50)->get();

        // DEBUG: Log kết quả
        \Log::info('getTournaments results', [
            'count' => $results->count(),
            'ids' => $results->pluck('id')->toArray(),
            'statuses' => $results->pluck('status')->toArray(),
            'created_bys' => $results->pluck('created_by')->toArray(),
            'start_dates' => $results->pluck('start_date')->toArray(),
        ]);

        return $results;
    }

    public function store(StoreActivityRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        if ($club->is_banned && !\App\Models\User::isSuperAdmin(auth()->id())) {
            return ResponseHelper::error('CLB này tạm thời bị cấm truy cập', 403);
        }

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $activity = $this->activityService->createActivity($club, $request->validated(), $userId);

            Cache::increment('club_content_version:' . $clubId);

            $activity->load([
                'creator' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'participants.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                }
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Tạo hoạt động thành công', 201);
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi tạo hoạt động', 403);
        }
    }

    public function show($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)
            ->with([
                'creator' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'club',
                'participants',
                'participants.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'participants.walletTransaction',
                'miniTournament',
                'fundCollection.contributions.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'fundCollection.assignedMembers' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
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
                'creator' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'participants.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                }
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Cập nhật hoạt động thành công');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi cập nhật hoạt động', 403);
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
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi xóa hoạt động', 403);
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
                'creator' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'participants.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                }
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Hoạt động đã được đánh dấu hoàn thành');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi đánh dấu hoàn thành', 403);
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
                'creator' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                },
                'participants.user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender', 'is_super_admin']);
                }
            ]);
            $activity->loadSum(self::ACTIVITY_COLLECTED_SUM, 'amount');

            return ResponseHelper::success(new ClubActivityResource($activity), 'Sự kiện đã được hủy');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi hủy hoạt động', 403);
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
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi hủy chuỗi hoạt động', 403);
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

    /**
     * Xóa tất cả cache entries của club content.
     * Bump version + forget cứng các variants phổ biến để immediate invalidation.
     */
    public static function forgetClubContentCache(int $clubId): void
    {
        Cache::increment('club_content_version:' . $clubId);

        $userId = auth()->id();
        $now = Carbon::now();
        $dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
        $dateTo = $now->copy()->endOfWeek()->format('Y-m-d');

        // Iterate over all combinations of category / status / per_page
        // and forget both guest and authenticated variants
        $categoryStatusCombos = [
            // scheduled/ongoing tab (with default date range)
            ['all', ['scheduled', 'ongoing']],
            ['activity', ['scheduled', 'ongoing']],
            ['mini_tournament', ['scheduled', 'ongoing']],
            ['tournament', ['scheduled', 'ongoing']],
            // history tab
            ['all', ['completed', 'cancelled']],
            ['activity', ['completed', 'cancelled']],
            ['mini_tournament', ['completed', 'cancelled']],
            ['tournament', ['completed', 'cancelled']],
            ['all', ['all']],
        ];

        foreach ($categoryStatusCombos as [$cat, $statuses]) {
            $hasDefaultDates = empty(array_diff($statuses, ['scheduled', 'ongoing']));
            foreach ([15, 20, 50] as $perPage) {
                $filters = ['category' => $cat, 'statuses' => $statuses, 'per_page' => $perPage];
                if ($hasDefaultDates) {
                    $filters['date_from'] = $dateFrom;
                    $filters['date_to'] = $dateTo;
                    $filters['include_next_occurrence_for_series_done_this_week'] = true;
                }
                foreach (['guest', $userId] as $userVariant) {
                    if ($userVariant === 'guest' || $userId) {
                        $key = 'club_content:' . $clubId . ':' . md5(json_encode($filters) . ':' . $userVariant);
                        Cache::forget($key);
                    }
                }
            }
        }
    }
}
