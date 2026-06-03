<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\FollowResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendPushJob;
use App\Models\CompetitionLocation;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class FollowController extends Controller
{
    /**
     * Danh sách theo dõi của người dùng
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:200',
            'is_map' => 'sometimes|boolean',
        ]);

        $perPage = $validated['per_page'] ?? Follow::PER_PAGE;
        $isMap = $validated['is_map'] ?? false;

        // Lấy toàn bộ follow, group theo loại
        $follows = Follow::where('user_id', $userId)
            ->with('followable')
            ->get()
            ->groupBy(fn($f) => strtolower(class_basename($f->followable_type)));

        $result = [];
        $meta = [];

        foreach ($follows as $shortType => $items) {
            $items = $items->values();

            if ($isMap) {
                // Nếu là chế độ bản đồ → load tất cả, không phân trang
                $result[$shortType] = FollowResource::collection($items);
                $meta[$shortType] = [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $items->count(),
                    'total' => $items->count(),
                ];
            } else {
                // Ngược lại: phân trang từng nhóm riêng
                $page = LengthAwarePaginator::resolveCurrentPage();
                $paginator = new LengthAwarePaginator(
                    $items->forPage($page, $perPage),
                    $items->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => request()->query()]
                );

                $result[$shortType] = FollowResource::collection($paginator);

                $meta[$shortType] = [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ];
            }
        }

        return ResponseHelper::success($result, 'Lấy danh sách theo dõi thành công', 200, $meta);
    }
    /**
     * Theo dõi một đối tượng
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'followable_type' => 'required|string',
            'followable_id' => 'required|integer',
        ]);

        $follow = $this->follow(Auth::id(), $validated['followable_type'], $validated['followable_id']);

        if (!$follow) {
            return ResponseHelper::error('Theo dõi thất bại', 400);
        }

        if (!$follow->wasRecentlyCreated) {
            return ResponseHelper::error('Bạn đã theo dõi rồi', 400);
        }

        $followable = $follow->followable;

        if ($followable instanceof User && $followable->id !== Auth::id()) {
            SendPushJob::dispatch(
                $followable->id,
                'Bạn có người theo dõi mới',
                Auth::user()->full_name . ' vừa theo dõi bạn',
                [
                    'type' => 'FOLLOW',
                    'follower_id' => Auth::id(),
                    'followable_id' => $followable->id,
                ]
            );
        }

        // Gửi notification cho chủ thể được follow
        if (method_exists($followable, 'notify')) {
            $followable->notify(new FollowNotification(Auth::user(), $followable));
        }

        return ResponseHelper::success(
            new FollowResource($follow->load('followable')),
            'Theo dõi thành công'
        );
    }
    protected function follow(int $userId, string $type, int $id): ?Follow
    {
        $map = [
            'competition' => CompetitionLocation::class,
            'user' => User::class,
        ];

        $model = $map[strtolower($type)] ?? null;
        if (!$model) {
            return null;
        }

        if ($model === User::class && $userId === $id) {
            return null;
        }

        if (!$model::whereKey($id)->exists()) {
            return null;
        }

        // Tạo hoặc lấy follow
        return Follow::firstOrCreate([
            'user_id' => $userId,
            'followable_id' => $id,
            'followable_type' => $model,
        ]);
    }
    /**
     * Hủy theo dõi một đối tượng
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'followable_type' => 'required|string',
            'followable_id' => 'required|integer',
        ]);

        $deleted = $this->unfollow(Auth::id(), $validated['followable_type'], $validated['followable_id']);

        if (!$deleted) {
            return ResponseHelper::error('Hủy theo dõi thất bại hoặc đối tượng không tồn tại', 400);
        }

        return ResponseHelper::success([], 'Hủy theo dõi thành công');
    }

    protected function unfollow(int $userId, string $type, int $id): bool
    {
        $map = [
            'competition' => CompetitionLocation::class,
            'user' => User::class,
        ];

        $model = $map[strtolower($type)] ?? null;
        if (!$model) {
            return false;
        }

        return Follow::where('user_id', $userId)
            ->where('followable_id', $id)
            ->where('followable_type', $model)
            ->delete() > 0;
    }

    /**
     * Lấy danh sách bạn bè (mutual follows)
     */
    public function getFriends(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:200',
            'is_map' => 'sometimes|boolean',
        ]);

        $userId = Auth::id();
        $perPage = $validated['per_page'] ?? 15;

        // Lấy danh sách user mà current user đang follow
        $followingIds = Follow::where('user_id', $userId)
            ->where('followable_type', User::class)
            ->pluck('followable_id');

        // Lấy danh sách user đang follow current user
        $followerIds = Follow::where('followable_type', User::class)
            ->where('followable_id', $userId)
            ->pluck('user_id');

        // Tìm giao = bạn bè
        $friendIds = $followingIds->intersect($followerIds)->values();

        // Thêm users cùng club active
        $sameClubIds = DB::table('club_members as cm1')
            ->join('club_members as cm2', 'cm1.club_id', '=', 'cm2.club_id')
            ->where('cm1.user_id', $userId)
            ->where('cm1.membership_status', 'joined')
            ->where('cm1.status', 'active')
            ->where('cm2.user_id', '!=', $userId)
            ->where('cm2.membership_status', 'joined')
            ->where('cm2.status', 'active')
            ->pluck('cm2.user_id');

        $friendIds = $friendIds->merge($sameClubIds)->unique()->values();

        $query = User::whereIn('id', $friendIds);

        // Nếu is_map = true → load toàn bộ (không phân trang)
        if ($validated['is_map'] ?? false) {
            $friends = $query->get();
            $meta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $friends->count(),
                'total' => $friends->count(),
            ];
        } else {
            $friends = $query->paginate($perPage);
            $meta = [
                'current_page' => $friends->currentPage(),
                'last_page' => $friends->lastPage(),
                'per_page' => $friends->perPage(),
                'total' => $friends->total(),
            ];
        }

        // Chuẩn hóa data
        $data = [
            'friends' => UserResource::collection($friends),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách bạn bè thành công', 200, $meta);
    }
}
