<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use App\Services\Admin\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $userManagementService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'nullable|string',
            'status' => 'nullable|in:banned,active,verified',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
        ]);

        $data = $this->userManagementService->search(
            $validated['page'] ?? 1,
            $validated['limit'] ?? 15,
            $validated['keyword'] ?? null,
            $validated['status'] ?? null
        );

        return ResponseHelper::paginated(
            $data->items(),
            [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
            ]
        );
    }

    public function show(int $id)
    {
        $user = $this->userManagementService->getDetail($id);
        return ResponseHelper::single($user, 'Lấy chi tiết user thành công');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'avatar' => 'nullable|image|max:2048',
            'name' => 'required|string|max:255',
            'vndupr_score' => 'nullable|numeric|min:0|max:10',
            'email' => 'required|unique:users,email|email',
            'password' => 'required|min:6|confirmed',
            'phone' => 'nullable|unique:users,phone|regex:/^[0-9]{10,11}$/',
            'gender' => 'nullable|in:0,1,2,3',
        ]);

        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
            $avatarName = 'avatars/' . uniqid() . '.' . $request->file('avatar')->getClientOriginalExtension();
            Storage::disk('public')->put($avatarName, file_get_contents($request->file('avatar')));
            $avatarUrl = asset('storage/' . $avatarName);
        }

        $userData = [
            'full_name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'avatar_url' => $avatarUrl,
            'email_verified_at' => now(),
            'is_profile_completed' => true,
        ];

        if (!empty($validated['phone'])) {
            $userData['phone'] = $validated['phone'];
        }

        if (!empty($validated['gender'])) {
            $userData['gender'] = $validated['gender'];
        }

        $user = User::create($userData);

        $userSport = UserSport::create([
            'user_id' => $user->id,
            'sport_id' => 1,
        ]);

        if (isset($validated['vndupr_score'])) {
            UserSportScore::create([
                'user_sport_id' => $userSport->id,
                'score_type' => UserSportScore::VNDUPR_SCORE,
                'score_value' => $validated['vndupr_score'],
            ]);
        }

        $user->load(['sports', 'badges', 'clubs']);

        return ResponseHelper::success($user, 'Tạo user thành công', 201);
    }

    public function ban(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $user = User::findOrFail($id);
        $admin = auth()->user();

        if ($user->id === $admin->id) {
            return ResponseHelper::error('Bạn không thể tự ban chính mình', 403, [
                'status_code' => 'CANNOT_SELF_BAN'
            ]);
        }

        if ($user->is_super_admin) {
            return ResponseHelper::error('Không thể ban tài khoản Super Admin', 403, [
                'status_code' => 'CANNOT_BAN_SUPER_ADMIN'
            ]);
        }

        $this->userManagementService->ban(
            $user,
            $validated['reason'] ?? null,
            $validated['note'] ?? null,
            $admin
        );

        return ResponseHelper::success(null, 'Ban user thành công');
    }

    public function unban(int $id)
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        if ($user->id === $admin->id) {
            return ResponseHelper::error('Bạn không thể tự unban chính mình', 403, [
                'status_code' => 'CANNOT_SELF_UNBAN'
            ]);
        }

        $this->userManagementService->unban($user, $admin);

        return ResponseHelper::success(null, 'Unban user thành công');
    }

    public function resetRating(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        $admin = auth()->user();

        $this->userManagementService->resetRating($user, $validated['reason'], $admin);

        return ResponseHelper::success(null, 'Reset rating thành công');
    }

    public function verify(int $id)
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        $this->userManagementService->verify($user, $admin);

        return ResponseHelper::success(null, 'Verify user thành công');
    }

    public function setAnchor(int $id)
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        $this->userManagementService->setAnchor($user, $admin);

        return ResponseHelper::success([
            'is_anchor' => $user->is_anchor,
        ], $user->is_anchor ? 'Đã set anchor' : 'Đã bỏ anchor');
    }
}
