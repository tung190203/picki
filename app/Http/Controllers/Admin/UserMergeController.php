<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserMergeExecuteRequest;
use App\Http\Requests\Admin\UserMergePreviewRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\UserManagementService;
use App\Services\Admin\UserMergeService;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMergeController extends Controller
{
    public function __construct(
        protected UserMergeService $userMergeService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:50',
        ]);

        $result = $this->userMergeService->searchUsers(
            $validated['q'] ?? '',
            $validated['page'] ?? 1,
            $validated['limit'] ?? 20
        );

        return ResponseHelper::paginated(
            UserResource::collection($result->items())->resolve(),
            [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
            'Tìm kiếm user thành công'
        );
    }

    public function preview(UserMergePreviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->userMergeService->preview(
            (int) $validated['user_a_id'],
            (int) $validated['user_b_id']
        );

        return ResponseHelper::success($result, 'Preview merge thành công');
    }

    public function previewFinal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_a_id' => ['required', 'integer', 'exists:users,id'],
            'user_b_id' => ['required', 'integer', 'exists:users,id', 'different:user_a_id'],
            'survivor_user_id' => ['required', 'integer', 'exists:users,id'],
            'duplicate_override' => ['required', 'boolean'],
        ]);

        $result = $this->userMergeService->previewFinal(
            (int) $validated['user_a_id'],
            (int) $validated['user_b_id'],
            (int) $validated['survivor_user_id'],
            (bool) $validated['duplicate_override']
        );

        return ResponseHelper::success($result, 'Preview cuối cùng thành công');
    }

    public function store(UserMergeExecuteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $admin = auth()->user();

        $userMerge = $this->userMergeService->execute(
            (int) $validated['user_a_id'],
            (int) $validated['user_b_id'],
            (int) $validated['survivor_user_id'],
            (bool) $validated['duplicate_override'],
            $validated['confirmation_name'],
            $admin
        );

        return ResponseHelper::success([
            'id' => $userMerge->id,
            'survivor_user_id' => $userMerge->survivor_user_id,
            'avatar_url' => $userMerge->survivor?->avatar_url,
            'merged_user_id' => $userMerge->merged_user_id,
            'status' => $userMerge->status,
            'matches_after_merge' => $userMerge->matches_after_merge,
            'final_rating' => $userMerge->final_rating,
        ], 'Merge user thành công', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'performed_by' => 'nullable|integer',
            'status' => 'nullable|in:pending,completed,failed',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $filters = array_filter([
            'search' => $validated['search'] ?? null,
            'performed_by' => $validated['performed_by'] ?? null,
            'status' => $validated['status'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        $result = $this->userMergeService->getMergeHistory(
            $validated['page'] ?? 1,
            $validated['limit'] ?? 15,
            $filters
        );

        $merges = collect($result->items());
        $users = $merges->pluck('survivor')->filter()->merge($merges->pluck('mergedUser')->filter())->unique('id');

        if ($users->isNotEmpty()) {
            app(UserManagementService::class)->preloadUserListData($users);
            $badges = app(BadgeService::class)->getBatchUserBadges($users->pluck('id')->all());
            request()->attributes->set('batch_badges', $badges);
        }

        $items = $merges->map(function ($merge) {
            return [
                'id' => $merge->id,
                'survivor' => $merge->survivor ? (new UserResource($merge->survivor))->resolve() : null,
                'merged_user' => $merge->mergedUser ? (new UserResource($merge->mergedUser))->resolve() : null,
                'matches_after_merge' => $merge->matches_after_merge,
                'duplicate_count' => $merge->duplicate_count,
                'duplicate_override' => $merge->duplicate_override,
                'performed_by' => [
                    'id' => $merge->performed_by,
                    'name' => $merge->performer?->full_name ?? 'N/A',
                ],
                'created_at' => $merge->created_at?->toISOString(),
            ];
        });

        return ResponseHelper::paginated(
            $items->toArray(),
            [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
            'Lấy lịch sử merge thành công'
        );
    }

    public function show(string $id): JsonResponse
    {
        $merge = $this->userMergeService->getMergeDetail((int) $id);

        $data = [
            'id' => $merge->id,
            'survivor' => [
                'id' => $merge->survivor_user_id,
                'name' => $merge->survivor?->full_name ?? $merge->metadata['survivor_snapshot']['full_name'] ?? 'N/A',
                'phone' => $merge->survivor?->phone ?? $merge->metadata['survivor_snapshot']['phone'] ?? null,
                'email' => $merge->survivor?->email ?? $merge->metadata['survivor_snapshot']['email'] ?? null,
            ],
            'merged_user' => [
                'id' => $merge->merged_user_id,
                'name' => $merge->mergedUser?->full_name ?? $merge->metadata['merged_snapshot']['full_name'] ?? 'N/A',
                'phone' => $merge->mergedUser?->phone ?? $merge->metadata['merged_snapshot']['phone'] ?? null,
                'email' => $merge->mergedUser?->email ?? $merge->metadata['merged_snapshot']['email'] ?? null,
            ],
            'match_summary' => [
                'survivor_matches' => $merge->matches_before_survivor,
                'merged_user_matches' => $merge->matches_before_merged,
                'duplicate_matches' => $merge->duplicate_count,
                'matches_after_merge' => $merge->matches_after_merge,
            ],
            'duplicate_matches' => $merge->metadata['duplicate_matches'] ?? [],
            'duplicate_override' => $merge->duplicate_override,
            'selected_info' => $merge->selected_info_source,
            'estimated_rating' => $merge->estimated_rating,
            'final_rating' => $merge->final_rating,
            'status' => $merge->status,
            'confirmation_name' => $merge->confirmation_name,
            'performed_by' => [
                'id' => $merge->performed_by,
                'name' => $merge->performer?->full_name ?? 'N/A',
            ],
            'created_at' => $merge->created_at?->toISOString(),
            'completed_at' => $merge->completed_at?->toISOString(),
            'metadata' => $merge->metadata,
        ];

        return ResponseHelper::success($data, 'Lấy chi tiết merge thành công');
    }
}
