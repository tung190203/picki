<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Http\Resources\UserResource;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use App\Services\Admin\UserManagementService;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserManagementController extends Controller
{
    public function __construct(
        protected UserManagementService $userManagementService,
        protected ImageOptimizationService $imageService
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

    public function show(string $id)
    {
        $user = $this->userManagementService->getDetail((int) $id);
        return ResponseHelper::single($user, 'Lấy chi tiết user thành công');
    }

    public function store(Request $request)
    {
        // Xác định có phải update hay create
        $targetUserId = $request->input('user_id');
        $isUpdate = $targetUserId !== null;

        // Validation rules khác nhau cho create vs update
        $phoneUniqueRule = $isUpdate ? 'unique:users,phone,' . $targetUserId : 'unique:users,phone';
        $rules = [
            'user_id' => 'nullable|exists:users,id',
            'avatar' => 'nullable|image|max:2048',
            'name' => $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255',
            'vndupr_score' => 'nullable|numeric|min:0|max:10',
            'email' => $isUpdate ? 'sometimes|email|unique:users,email,' . $targetUserId : 'required|unique:users,email|email',
            'password' => $isUpdate ? 'nullable|min:6|confirmed' : 'required|min:6|confirmed',
            'phone' => 'nullable|' . $phoneUniqueRule . '|regex:/^[0-9]{10,11}$/',
            'gender' => 'nullable|in:0,1,2,3',
            'club_ids' => 'nullable|array',
            'club_ids.*' => 'integer|exists:clubs,id',
        ];

        $validated = $request->validate($rules);

        // Nếu có user_id → UPDATE user
        if ($isUpdate) {
            return $this->updateUser($request, $validated, $targetUserId);
        }

        // Nếu không có user_id → TẠO user mới (logic cũ)
        return $this->createUser($request, $validated);
    }

    private function updateUser(Request $request, array $validated, int $userId)
    {
        $user = User::findOrFail($userId);

        // Update avatar nếu có
        if ($request->hasFile('avatar')) {
            $this->imageService->deleteOldImage($user->avatar_url);
            $avatarName = 'avatars/' . uniqid() . '.' . $request->file('avatar')->getClientOriginalExtension();
            Storage::disk('public')->put($avatarName, file_get_contents($request->file('avatar')));
            $user->avatar_url = asset('storage/' . $avatarName);
        }

        if (isset($validated['name'])) {
            $user->full_name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (isset($validated['gender'])) {
            $user->gender = $validated['gender'];
        }
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        // Update vndupr_score nếu có
        if (isset($validated['vndupr_score'])) {
            $userSport = UserSport::where('user_id', $userId)->where('sport_id', 1)->first();
            if ($userSport) {
                UserSportScore::updateOrCreate(
                    ['user_sport_id' => $userSport->id, 'score_type' => UserSportScore::VNDUPR_SCORE],
                    ['score_value' => $validated['vndupr_score']]
                );
            }
        }

        // Update club memberships nếu có
        if (isset($validated['club_ids'])) {
            ClubMember::where('user_id', $userId)->delete();
            foreach ($validated['club_ids'] as $clubId) {
                ClubMember::create([
                    'club_id' => $clubId,
                    'user_id' => $user->id,
                    'role' => ClubMemberRole::Member,
                    'membership_status' => ClubMembershipStatus::Joined,
                    'status' => ClubMemberStatus::Active,
                    'joined_at' => now(),
                ]);
            }
        }

        $user->load(['sports.sport', 'sports.scores', 'userBadges', 'clubs']);

        return ResponseHelper::success(new UserResource($user), 'Cập nhật user thành công');
    }

    private function createUser(Request $request, array $validated)
    {
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

        if (!empty($validated['club_ids'])) {
            foreach ($validated['club_ids'] as $clubId) {
                ClubMember::create([
                    'club_id' => $clubId,
                    'user_id' => $user->id,
                    'role' => ClubMemberRole::Member,
                    'membership_status' => ClubMembershipStatus::Joined,
                    'status' => ClubMemberStatus::Active,
                    'joined_at' => now(),
                ]);
            }
        }

        $user->load(['sports.sport', 'sports.scores', 'userBadges', 'clubs']);

        return ResponseHelper::success(new UserResource($user), 'Tạo user thành công', 201);
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

    public function setPicki(int $id)
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        $this->userManagementService->setPicki($user, $admin);

        return ResponseHelper::success(null, 'Đã cấp Picki badge');
    }

    public function revokePicki(int $id)
    {
        $user = User::findOrFail($id);
        $admin = auth()->user();

        $this->userManagementService->revokePicki($user, $admin);

        return ResponseHelper::success(null, 'Đã thu hồi Picki badge');
    }
}
