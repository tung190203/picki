<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\ApproveJoinRequestRequest;
use App\Http\Requests\Club\GetJoinRequestsRequest;
use App\Http\Requests\Club\RejectJoinRequestRequest;
use App\Http\Requests\Club\SendJoinRequestRequest;
use App\Http\Resources\Club\ClubMemberResource;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Services\Club\ClubJoinRequestService;
use Illuminate\Http\Request;

class ClubJoinRequestController extends Controller
{
    public function __construct(
        protected ClubJoinRequestService $joinRequestService
    ) {
    }

    public function index(GetJoinRequestsRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xem yêu cầu tham gia', 403);
        }

        $requests = $this->joinRequestService->getJoinRequests($club, $request->validated());

        $meta = [
            'current_page' => $requests->currentPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
            'last_page' => $requests->lastPage(),
        ];

        return ResponseHelper::success(
            ClubMemberResource::collection($requests),
            'Lấy danh sách yêu cầu tham gia thành công',
            200,
            $meta
        );
    }

    public function store(SendJoinRequestRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập để gửi yêu cầu tham gia', 401);
        }

        try {
            $member = $this->joinRequestService->sendJoinRequest($club, $userId, $request->input('message'));
            $member->load(['user' => User::FULL_RELATIONS, 'club']);

            return ResponseHelper::success(new ClubMemberResource($member), 'Yêu cầu tham gia đã được gửi', 201);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }
    }

    public function show($clubId, $requestId)
    {
        $member = ClubMember::where('club_id', $clubId)
            ->with(['user' => User::FULL_RELATIONS, 'club', 'reviewer', 'inviter'])
            ->find($requestId);

        if (!$member) {
            return ResponseHelper::error('Không tìm thấy yêu cầu tham gia trong CLB này', 404);
        }

        return ResponseHelper::success(new ClubMemberResource($member), 'Lấy chi tiết yêu cầu thành công');
    }

    public function destroyMyRequest($clubId)
    {
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->joinRequestService->cancelMyRequest(Club::findOrFail($clubId), $userId);
            return ResponseHelper::success('Yêu cầu đã được hủy');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    public function approve(ApproveJoinRequestRequest $request, $clubId, $requestId = null)
    {
        $club = Club::findOrFail($clubId);
        $reviewerId = auth()->id();

        if (!$club->canManage($reviewerId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền duyệt', 403);
        }

        if (!empty($request->input('user_id'))) {
            $member = ClubMember::where('club_id', $clubId)
                ->where('user_id', $request->input('user_id'))
                ->where('membership_status', \App\Enums\ClubMembershipStatus::Pending)
                ->first();

            if (!$member) {
                return ResponseHelper::error('Không tìm thấy yêu cầu tham gia nào của user này', 404);
            }
        } else {
            if (!$requestId) {
                return ResponseHelper::error('Cần cung cấp user_id trong body hoặc requestId trong URL', 400);
            }

            $member = ClubMember::where('club_id', $clubId)
                ->where('membership_status', \App\Enums\ClubMembershipStatus::Pending)
                ->find($requestId);

            if (!$member) {
                return ResponseHelper::error('Không tìm thấy yêu cầu tham gia đang chờ duyệt trong CLB này', 404);
            }
        }

        try {
            $member = $this->joinRequestService->approveRequest($member, $reviewerId, $request->input('role'));
            $member->load(['user' => User::FULL_RELATIONS, 'reviewer']);

            return ResponseHelper::success(new ClubMemberResource($member), 'Yêu cầu đã được duyệt');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function reject(RejectJoinRequestRequest $request, $clubId, $requestId = null)
    {
        $club = Club::findOrFail($clubId);
        $reviewerId = auth()->id();

        if (!$club->canManage($reviewerId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền từ chối', 403);
        }

        if (!empty($request->input('user_id'))) {
            $member = ClubMember::where('club_id', $clubId)
                ->where('user_id', $request->input('user_id'))
                ->where('membership_status', \App\Enums\ClubMembershipStatus::Pending)
                ->first();

            if (!$member) {
                return ResponseHelper::error('Không tìm thấy yêu cầu tham gia nào của user này', 404);
            }
        } else {
            if (!$requestId) {
                return ResponseHelper::error('Cần cung cấp user_id trong body hoặc requestId trong URL', 400);
            }

            $member = ClubMember::where('club_id', $clubId)
                ->where('membership_status', \App\Enums\ClubMembershipStatus::Pending)
                ->find($requestId);

            if (!$member) {
                return ResponseHelper::error('Không tìm thấy yêu cầu tham gia đang chờ duyệt trong CLB này', 404);
            }
        }

        try {
            $this->joinRequestService->rejectRequest($member, $reviewerId, $request->input('rejection_reason'));
            return ResponseHelper::success([], 'Yêu cầu đã bị từ chối');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function myInvitations(Request $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $invitations = $this->joinRequestService->getMyInvitations($userId);

        return ResponseHelper::success(
            ClubMemberResource::collection($invitations),
            'Lấy danh sách lời mời tham gia CLB thành công'
        );
    }

    public function acceptInvitation($clubId)
    {
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ($clubId === '' || $clubId === null || !is_numeric($clubId) || (int) $clubId < 1) {
            return ResponseHelper::error('Thiếu hoặc sai clubId. Vui lòng truyền ID CLB trong URL: POST /api/clubs/{clubId}/invitations/accept', 400);
        }
        $clubId = (int) $clubId;

        try {
            $member = $this->joinRequestService->acceptInvitation($clubId, $userId);
            $member->load(['user' => User::FULL_RELATIONS, 'club', 'inviter']);

            return ResponseHelper::success(new ClubMemberResource($member), 'Bạn đã tham gia CLB thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }

    public function rejectInvitation(Request $request, $clubId)
    {
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ($clubId === '' || $clubId === null || !is_numeric($clubId) || (int) $clubId < 1) {
            return ResponseHelper::error('Thiếu hoặc sai clubId. Vui lòng truyền ID CLB trong URL: POST /api/clubs/{clubId}/invitations/reject', 400);
        }
        $clubId = (int) $clubId;

        try {
            $this->joinRequestService->rejectInvitation($clubId, $userId);
            return ResponseHelper::success('Đã từ chối lời mời tham gia CLB');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 404);
        }
    }
}
