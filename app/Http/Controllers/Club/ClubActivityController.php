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
use App\Models\Club\Club;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubMember;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Services\BadgeService;
use App\Services\Club\ClubActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClubActivityController extends Controller
{
    private const ACTIVITY_COLLECTED_SUM = 'activityFeeTransactions as collected_amount';

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

        $category = $filters['category'] ?? 'all';
        $filters['category'] = $category;

        unset($filters['statuses']);

        $clientSentDate = $request->has('date_from') || $request->has('from_date') || $request->has('date_to') || $request->has('to_date');
        if ($clientSentDate) {
            if (empty($filters['date_from']) && $request->has('from_date')) {
                $filters['date_from'] = $request->input('from_date');
            }
            if (empty($filters['date_to']) && $request->has('to_date')) {
                $filters['date_to'] = $request->input('to_date');
            }
        }

        $isClubStaff = $userId && ClubMember::where('club_id', $club->id)
            ->where('user_id', $userId)
            ->whereIn('role', [
                ClubMemberRole::Admin,
                ClubMemberRole::Manager,
                ClubMemberRole::Secretary,
                ClubMemberRole::Treasurer,
            ])
            ->exists();
        $activeItems = $this->fetchContents($club, $category, $filters, $userId, 'active', $isClubStaff);
        $endedItems = $this->fetchContents($club, $category, $filters, $userId, 'ended', $isClubStaff);

        $activeItems = $activeItems->sortBy(fn($i) => $i['data']->resource->start_time ?? '')->values();
        $endedItems = $endedItems->sortByDesc(fn($i) => $i['data']->resource->start_time ?? '')->values();

        $merged = $activeItems->merge($endedItems)->values();
        $totalCount = $merged->count();

        $perPage = $filters['per_page'] ?? 15;
        $currentPage = $filters['page'] ?? 1;
        $offset = ($currentPage - 1) * $perPage;
        $paginatedItems = $merged->slice($offset, $perPage)->values();

        $this->preloadUserCardData($paginatedItems);

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

    private function fetchContents(Club $club, string $category, array $filters, ?int $userId, string $group, bool $isClubStaff = false): \Illuminate\Support\Collection
    {
        $statuses = $group === 'active'
            ? ['scheduled', 'ongoing']
            : ['completed', 'cancelled'];

        $groupFilters = $filters;
        $groupFilters['statuses'] = $statuses;

        $items = collect();

        if ($category === 'all' || $category === 'activity') {
            $activities = $this->activityService->getActivities($club, $groupFilters, $userId);
            foreach ($activities->items() as $activity) {
                $items->push([
                    'id' => $activity->id,
                    'type' => 'activity',
                    'data' => new ClubActivityListResource($activity),
                ]);
            }
        }

        if ($category === 'all' || $category === 'mini_tournament') {
            $miniTournaments = $this->getMiniTournaments($club, $groupFilters, $userId, $statuses, $isClubStaff);
            $this->preloadMiniListRelations($miniTournaments);
            foreach ($miniTournaments as $tournament) {
                $items->push([
                    'id' => $tournament->id,
                    'type' => 'mini_tournament',
                    'data' => new \App\Http\Resources\ListMiniTournamentResource($tournament),
                ]);
            }
        }

        if ($category === 'all' || $category === 'tournament') {
            $tournaments = $this->getTournaments($club, $groupFilters, $userId, $statuses, $isClubStaff);
            foreach ($tournaments as $tournament) {
                $items->push([
                    'id' => $tournament->id,
                    'type' => 'tournament',
                    'data' => new \App\Http\Resources\ListTournamentResource($tournament),
                ]);
            }
        }

        return $items;
    }

    /**
     * Batch preload user-card data cho mọi user xuất hiện trong items của group
     */
    private function preloadUserCardData(\Illuminate\Support\Collection $items): void
    {
        $users = collect();
        foreach ($items as $item) {
            $resource = $item['data']->resource ?? null;
            if (!$resource) {
                continue;
            }
            match ($item['type']) {
                'activity' => $users->push(
                    $resource->creator,
                    ...$resource->relationLoaded('participants')
                        ? $resource->participants->map(fn ($p) => $p->user)
                        : []
                ),
                'mini_tournament' => $users->push(
                    $resource->creator,
                    ...$resource->relationLoaded('participants') ? $resource->participants->flatMap(
                        fn ($p) => [$p->user, $p->guarantor, $p->invitedBy]
                    ) : [],
                    ...$resource->relationLoaded('staff') ? $resource->staff : [],
                ),
                'tournament' => $users->push(
                    $resource->createdBy,
                    ...$resource->relationLoaded('participants')
                        ? $resource->participants->map(fn ($p) => $p->user)
                        : []
                ),
                default => null,
            };
        }

        $users = $users->filter()->unique('id')->values();
        if ($users->isEmpty()) {
            return;
        }
        $userIds = $users->pluck('id')->all();

        // ponytail: 1 query — trước đó UserResource::$this->platform query per user.
        // $users là Support collection nên không loadMissing được → batch 1 query rồi setRelation.
        $tokensByUser = \App\Models\DeviceToken::whereIn('user_id', $userIds)
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('user_id');
        foreach ($users as $u) {
            $u->setRelation('deviceTokens', $tokensByUser->get($u->id, collect()));
        }

        request()->attributes->set(
            'batch_badges',
            app(BadgeService::class)->getBatchUserBadges($userIds)
        );

        $counts = DB::table('mini_tournament_staff as mts')
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mts.mini_tournament_id')
            ->join('mini_participants as mp', 'mp.mini_tournament_id', '=', 'mnt.id')
            ->whereIn('mts.user_id', $userIds)
            ->where('mts.role', \App\Models\MiniTournamentStaff::ROLE_ADMIN)
            ->where('mnt.status', '!=', MiniTournament::STATUS_DRAFT)
            ->where('mnt.status', '!=', MiniTournament::STATUS_CANCELLED)
            ->where('mp.is_confirmed', 1)
            ->select('mts.user_id', DB::raw('COUNT(DISTINCT mnt.id) as cnt'))
            ->groupBy('mts.user_id')
            ->pluck('cnt', 'user_id');

        foreach ($users as $user) {
            $user->setAttribute(
                'has_advanced_mini_tournament',
                ((int) ($counts[$user->id] ?? 0)) >= 3
            );
        }
    }

    /**
     * Preload quan hệ mà ListMiniTournamentResource cần cho list:
     * staff.user (MiniTournamentStaffResource find() per row), guarantor, invitedBy,
     * user.sports (UserListResource) và guarantor_participant_id (batch 1 query).
     */
    private function preloadMiniListRelations($tournaments): void
    {
        $tournaments = $tournaments instanceof \Illuminate\Support\Collection
            ? $tournaments
            : collect($tournaments);
        if ($tournaments->isEmpty()) {
            return;
        }

        $tournaments->loadMissing([
            'staff.sports.scores',
            'staff.sports.sport',
            'participants.guarantor',
            'participants.invitedBy',
            'participants.user.sports.scores',
            'participants.user.sports.sport',
        ]);

        $participants = $tournaments->flatMap(fn ($t) => $t->relationLoaded('participants') ? $t->participants : []);
        $guestRows = $participants->filter(fn ($p) => $p->is_guest && $p->guarantor_user_id);
        if ($guestRows->isEmpty()) {
            return;
        }

        $byTournament = $guestRows->groupBy('mini_tournament_id');
        $map = [];
        // ponytail: 1 query cho mọi giải — trước đó MiniTournament::find() + pluck per giải.
        $allGuarantorIds = $guestRows->pluck('guarantor_user_id')->unique()->values()->all();
        // query 1 lần rồi group trong PHP (pluck composite key không hỗ trợ trực tiếp).
        $rows = \App\Models\MiniParticipant::whereIn('mini_tournament_id', $byTournament->keys()->all())
            ->whereIn('user_id', $allGuarantorIds)
            ->get(['id', 'mini_tournament_id', 'user_id'])
            ->groupBy('mini_tournament_id');
        foreach ($byTournament as $tournamentId => $tournamentRows) {
            $idByUser = isset($rows[$tournamentId])
                ? $rows[$tournamentId]->pluck('id', 'user_id')
                : collect();
            foreach ($tournamentRows as $row) {
                $map[$row->id] = $idByUser[$row->guarantor_user_id] ?? null;
            }
        }

        foreach ($participants as $p) {
            $p->setAttribute('_guarantor_participant_id', $map[$p->id] ?? null);
        }
    }

    /**
     * Lấy danh sách mini tournaments của club
     */
    private function getMiniTournaments(Club $club, array $filters, ?int $userId, array $statuses, bool $isClubStaff = false)
    {
        // ponytail: eager-load lean cho list — withFullRelations() kéo cả matches + cây bracket + 3 selectSub.
        // 'fundCollection' bỏ: list chỉ đọc cột club_fund_collection_id, không render relation.
        $query = MiniTournament::with([
                'sport',
                'competitionLocation',
                'club' => fn ($q) => $q->withCount(['members', 'guestProfiles']),
                'creator',
                'staff',
                'participants.user',
                'participants.guarantor',
                'participants.invitedBy',
            ])
            ->withCount([
                'participantPayments as paid_payments_count' => fn ($q) => $q->whereIn('status', ['paid', 'confirmed']),
            ])
            ->where('club_id', $club->id);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $hasAll = in_array('all', $statuses);
        $isHistoryOnly = !empty($statuses)
            && empty(array_diff($statuses, ['completed', 'cancelled']));

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
        $normalStatuses = [
            MiniTournament::STATUS_OPEN,
            MiniTournament::STATUS_CLOSED,
            MiniTournament::STATUS_CANCELLED,
        ];

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
        $query->where(function ($q) use ($userId, $filterStatuses, $dateCondition, $isClubStaff, $isHistoryOnly) {
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

                // Tab "Đã kết thúc": chỉ hiện kèo thực sự đã qua (start_time <= now)
                // Tránh kèo có status=closed nhưng start_time trong tương lai do data bị set sai
                if ($isHistoryOnly) {
                    $q->where('start_time', '<=', now());
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
                $q->orWhere(function ($sub) use ($publicStatuses, $dateCondition, $isHistoryOnly) {
                    $sub->whereIn('status', $publicStatuses);

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }

                    // Tab "Đã kết thúc": chỉ hiện kèo thực sự đã qua
                    if ($isHistoryOnly) {
                        $sub->where('start_time', '<=', now());
                    }
                });
            }
        });

        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';
        $query->orderBy('start_time', $orderDirection);
        $results = $query->limit($filters['per_page'] ?? 50)->get();
        return $results;
    }

    /**
     * Lấy danh sách tournaments của club
     */
    private function getTournaments(Club $club, array $filters, ?int $userId, array $statuses, bool $isClubStaff = false)
    {
        // ponytail: eager-load lean cho list — withFullRelations() kéo cả cây tournamentTypes.groups.matches.
        $query = Tournament::with([
                'createdBy',
                'club' => fn ($q) => $q->withCount(['members', 'guestProfiles']),
                'sport',
                'competitionLocation',
                'tournamentStaffs',
                'participants.user',
                'participants.guarantor',
            ])->where('club_id', $club->id);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

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
        $query->where(function ($q) use ($userId, $filterStatuses, $dateCondition, $isClubStaff, $isHistoryOnly) {
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

                // Tab "Đã kết thúc": chỉ hiện giải thực sự đã qua (start_date <= now)
                // Tránh giải có status=closed nhưng start_date trong tương lai do data bị set sai
                if ($isHistoryOnly) {
                    $q->where('start_date', '<=', now());
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
                $q->orWhere(function ($sub) use ($publicStatuses, $dateCondition, $isHistoryOnly) {
                    $sub->whereIn('status', $publicStatuses);

                    // Áp dụng date filter
                    foreach ($dateCondition as $cond) {
                        $sub->whereDate($cond['column'], $cond['operator'], $cond['value']);
                    }

                    // Tab "Đã kết thúc": chỉ hiện giải thực sự đã qua
                    if ($isHistoryOnly) {
                        $sub->where('start_date', '<=', now());
                    }
                });
            }
        });

        $orderDirection = $isHistoryOnly ? 'desc' : 'asc';
        $query->orderBy('start_date', $orderDirection);

        $results = $query->limit($filters['per_page'] ?? 50)->get();
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
                    // NOTE: Khi eager load belongsToMany với select() không prefix,
                    // cột 'id' sẽ ambiguous vì bảng pivot cũng có cột 'id'.
                    // Luôn dùng table prefix để tránh lỗi.
                    $q->select(
                        'users.id',
                        'users.full_name',
                        'users.avatar_url',
                        'users.email',
                        'users.gender',
                        'users.is_super_admin',
                    );
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
