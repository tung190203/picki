<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Club\ClubGuestProfileResource;
use App\Models\Club\Club;
use App\Models\Club\ClubGuestProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubGuestProfileController extends Controller
{
    /**
     * GET /api/clubs/{clubId}/guests/profiles
     * Lấy danh sách CLB guest của CLB. Search theo tên user.
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xem danh sách CLB guest', 403);
        }

        $query = $club->guestProfiles()->with('user');

        if ($search = $request->input('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%');
            });
        }

        $profiles = $query->orderBy('created_at', 'desc')->get();

        return ResponseHelper::success(
            ClubGuestProfileResource::collection($profiles),
            'Lấy danh sách CLB guest thành công'
        );
    }

    /**
     * POST /api/clubs/{clubId}/guests/profiles
     * Tạo mới 1 CLB guest (tìm/tạo User.is_guest=true + ClubGuestProfile).
     */
    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền tạo CLB guest', 403);
        }

        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'estimated_level' => 'nullable|numeric|min:1|max:8',
        ]);

        $user = $this->findOrCreateGuestUser($data, $request);

        $profile = ClubGuestProfile::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'estimated_level' => $data['estimated_level'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $profile->load('user');

        return ResponseHelper::success(
            new ClubGuestProfileResource($profile),
            'Tạo CLB guest thành công',
            201
        );
    }

    /**
     * PUT /api/clubs/{clubId}/guests/profiles/{id}
     * Sửa estimated_level + sync name/avatar/phone của user.
     */
    public function update(Request $request, $clubId, $id)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền sửa CLB guest', 403);
        }

        $profile = ClubGuestProfile::where('club_id', $club->id)->findOrFail($id);

        $data = $request->validate([
            'guest_name' => 'nullable|string|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'estimated_level' => 'nullable|numeric|min:1|max:8',
        ]);

        DB::transaction(function () use ($profile, $data, $request) {
            $userUpdates = [];

            if (array_key_exists('guest_name', $data) && $data['guest_name'] !== null) {
                $userUpdates['full_name'] = trim($data['guest_name']);
            }
            if (array_key_exists('guest_phone', $data)) {
                $userUpdates['phone'] = $data['guest_phone'];
            }

            $uploadedFile = $request->file('guest_avatar');
            if ($uploadedFile && $uploadedFile->isValid()) {
                $path = $uploadedFile->store('guest-avatars', 'public');
                $userUpdates['avatar_url'] = asset('storage/' . $path);
            }

            if (!empty($userUpdates)) {
                $profile->user->updateQuietly($userUpdates);
            }

            if (array_key_exists('estimated_level', $data)) {
                $profile->update(['estimated_level' => $data['estimated_level']]);
            }
        });

        $profile->load('user');

        return ResponseHelper::success(
            new ClubGuestProfileResource($profile),
            'Cập nhật CLB guest thành công'
        );
    }

    /**
     * DELETE /api/clubs/{clubId}/guests/profiles/{id}
     * Soft-delete profile. Không xoá User vì user có thể thuộc nhiều CLB.
     */
    public function destroy($clubId, $id)
    {
        $club = Club::findOrFail($clubId);

        if (!$club->canManage(auth()->id())) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xóa CLB guest', 403);
        }

        $profile = ClubGuestProfile::where('club_id', $club->id)->findOrFail($id);
        $profile->delete();

        return ResponseHelper::success(null, 'Đã xóa CLB guest khỏi CLB');
    }

    /**
     * Tìm user theo phone (nếu có) hoặc tạo mới User.is_guest=true.
     * Nếu phone trùng user thật → không set is_guest (giữ nguyên user thật).
     */
    private function findOrCreateGuestUser(array $data, Request $request): User
    {
        $avatarUrl = null;
        $uploadedFile = $request->file('guest_avatar');
        if ($uploadedFile && $uploadedFile->isValid()) {
            $path = $uploadedFile->store('guest-avatars', 'public');
            $avatarUrl = asset('storage/' . $path);
        }

        if (!empty($data['guest_phone'])) {
            $user = User::where('phone', $data['guest_phone'])->first();
            if (!$user) {
                return User::create([
                    'full_name' => $data['guest_name'],
                    'phone' => $data['guest_phone'],
                    'avatar_url' => $avatarUrl,
                    'password' => Str::random(12),
                    'visibility' => User::VISIBILITY_PRIVATE,
                    'is_guest' => true,
                    'last_active_at' => now(),
                ]);
            }
            // Phone đã tồn tại: nếu là guest cũ → cập nhật; user thật → giữ nguyên, chỉ update avatar
            $updates = [];
            if ($avatarUrl) {
                $updates['avatar_url'] = $avatarUrl;
            }
            $updates['last_active_at'] = now();
            if ($user->is_guest) {
                $updates['full_name'] = $data['guest_name'];
            }
            $user->updateQuietly($updates);
            return $user;
        }

        // Không có phone → tạo user guest mới
        return User::create([
            'full_name' => $data['guest_name'],
            'phone' => null,
            'avatar_url' => $avatarUrl,
            'password' => Str::random(12),
            'visibility' => User::VISIBILITY_PRIVATE,
            'is_guest' => true,
            'last_active_at' => now(),
        ]);
    }
}
