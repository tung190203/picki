<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Club\ClubGuestResource;
use App\Models\Club\Club;
use App\Services\Club\ClubGuestService;
use Illuminate\Http\Request;

class ClubGuestController extends Controller
{
    public function __construct(protected ClubGuestService $guestService) {}

    /**
     * GET /api/clubs/{clubId}/guests
     * Trả về 2 nhóm khách (normal / potential) cho Admin/Manager/Secretary.
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xem danh sách khách', 403);
        }

        $oneMonthAgo = now()->subDays(30);
        $segments = $this->guestService->getGuestsWithSegments($club, $oneMonthAgo);

        $normal = $segments['normal'];
        $potential = $segments['potential'];

        return ResponseHelper::success([
            'normal' => ClubGuestResource::collection($normal),
            'potential' => ClubGuestResource::collection($potential),
            'counts' => [
                'normal' => $normal->count(),
                'potential' => $potential->count(),
                'total' => $normal->count() + $potential->count(),
            ],
        ], 'Lấy danh sách khách thành công');
    }

    /**
     * DELETE /api/clubs/{clubId}/guests/{userId}
     * Xoá khách khỏi club_guests.
     */
    public function destroy(Request $request, $clubId, $userId)
    {
        $club = Club::findOrFail($clubId);
        $authId = auth()->id();

        if (!$club->canManage($authId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xóa khách', 403);
        }

        $this->guestService->deleteGuest($club, (int) $userId);
        return ResponseHelper::success([], 'Đã xóa khách khỏi CLB');
    }

    /**
     * POST /api/clubs/{clubId}/guests/invite
     * Body: { user_id: <int> }
     * Mời guest tham gia CLB — gọi lại inviteMember logic.
     */
    public function invite(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền mời khách', 403);
        }

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $this->guestService->inviteGuestToClub($club, (int) $data['user_id'], $userId);
            return ResponseHelper::success(['is_invited' => true], 'Đã gửi lời mời tham gia CLB');
        } catch (\App\Exceptions\BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi mời khách: ' . $e->getMessage(), 400);
        }
    }
}
