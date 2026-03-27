<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\CheckInRequest;
use App\Http\Requests\Club\GetParticipantsRequest;
use App\Http\Requests\Club\InviteParticipantsRequest;
use App\Http\Requests\Club\UpdateParticipantRequest;
use App\Http\Resources\Club\ClubActivityParticipantResource;
use App\Enums\ClubActivityParticipantStatus;
use App\Models\Club\ClubActivity;
use App\Models\Club\ClubActivityParticipant;
use App\Services\Club\ClubActivityParticipantService;
use Illuminate\Support\Facades\Cache;

class ClubActivityParticipantController extends Controller
{
    public function __construct(
        protected ClubActivityParticipantService $participantService
    ) {
    }

    public function index(GetParticipantsRequest $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);

        $result = $this->participantService->getParticipants(
            $activity,
            $request->input('status')
        );

        $data = [
            'participants' => ClubActivityParticipantResource::collection($result['participants']),
            'total' => $result['total'],
            'pending_count' => $result['pending_count'],
            'invited_count' => $result['invited_count'],
            'accepted_count' => $result['accepted_count'],
            'attended_count' => $result['attended_count'] ?? 0,
        ];

        return ResponseHelper::success($data, 'Lấy danh sách người tham gia thành công');
    }

    public function store($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->joinActivity($activity, $userId);
            $participant->load(['user', 'walletTransaction']);

            $message = in_array($participant->status, [ClubActivityParticipantStatus::Accepted, ClubActivityParticipantStatus::Attended])
                ? 'Đã tham gia hoạt động'
                : 'Đã gửi yêu cầu tham gia, chờ admin duyệt';

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                $message,
                201
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'đã tham gia') ? 409 :
                         (str_contains($e->getMessage(), 'đủ số lượng') ? 422 : 403);
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function invite(InviteParticipantsRequest $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $result = $this->participantService->inviteUsers(
                $activity,
                $request->input('user_ids'),
                $userId
            );

            $data = [
                'invited_count' => $result['invited_count'],
                'participants' => ClubActivityParticipantResource::collection(collect($result['participants'])),
            ];

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success($data, 'Đã mời thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function update(UpdateParticipantRequest $request, $clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user'])
            ->findOrFail($participantId);

        try {
            $participant = $this->participantService->updateParticipantStatus(
                $participant,
                $request->input('status')
            );

            $participant->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Cập nhật trạng thái thành công'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function destroy($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->participantService->deleteParticipant($participant, $userId);
            Cache::increment('club_content_version:' . $clubId);
            return ResponseHelper::success('Xóa người tham gia thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function approve($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user'])
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->approveRequest($participant, $userId);
            $participant->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã duyệt yêu cầu tham gia'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'pending') || str_contains($e->getMessage(), 'đủ số lượng') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function reject($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user'])
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->rejectRequest($participant, $userId);
            $participant->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã từ chối yêu cầu tham gia'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'pending') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function acceptInvite($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user'])
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->acceptInvite($participant, $userId);
            $participant->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã chấp nhận tham gia sự kiện'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'invited') || str_contains($e->getMessage(), 'đủ số lượng') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function declineInvite($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user'])
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->declineInvite($participant, $userId);
            $participant->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã từ chối lời mời tham gia'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'invited') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function cancel($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->participantService->cancelRequest($participant, $userId);
            Cache::increment('club_content_version:' . $clubId);
            return ResponseHelper::success('Đã hủy yêu cầu tham gia');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'pending') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function withdraw($clubId, $activityId, $participantId)
    {
        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->with(['activity', 'user', 'walletTransaction'])
            ->findOrFail($participantId);

        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $result = $this->participantService->withdraw($participant, $userId);
            $result['participant']->load(['user', 'walletTransaction']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($result['participant']),
                $result['message']
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'scheduled') || str_contains($e->getMessage(), 'chấp nhận') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function selfCheckIn($clubId, $activityId)
    {
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->where('user_id', $userId)
            ->with(['activity', 'user'])
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Bạn chưa tham gia hoạt động này', 422);
        }

        try {
            $participant = $this->participantService->manualCheckIn($participant, $userId);
            $participant->load(['user']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã check-in hoạt động'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'đã bị hủy') ||
                         str_contains($e->getMessage(), 'kết thúc') ||
                         str_contains($e->getMessage(), 'được duyệt') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function selfMarkAbsent($clubId, $activityId)
    {
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $participant = ClubActivityParticipant::whereHas('activity', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_activity_id', $activityId)
            ->where('user_id', $userId)
            ->with(['activity', 'user'])
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Bạn chưa tham gia hoạt động này', 422);
        }

        try {
            $participant = $this->participantService->markAbsent($participant, $userId);
            $participant->load(['user']);

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                'Đã báo vắng cho người tham gia'
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'đã bị hủy') ||
                         str_contains($e->getMessage(), 'kết thúc') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function checkIn(CheckInRequest $request, $clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $participant = $this->participantService->checkIn(
                $activity,
                $request->input('token'),
                $userId
            );

            $participant->load('user');

            $message = $participant->checked_in_at && $participant->checked_in_at->lt(now()->subSeconds(5))
                ? 'Bạn đã check-in trước đó'
                : 'Check-in thành công';

            Cache::increment('club_content_version:' . $clubId);

            return ResponseHelper::success(
                new ClubActivityParticipantResource($participant),
                $message
            );
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'bị hủy') ||
                         str_contains($e->getMessage(), 'chưa tham gia') ||
                         str_contains($e->getMessage(), 'được duyệt') ? 422 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function checkInList($clubId, $activityId)
    {
        $activity = ClubActivity::where('club_id', $clubId)->findOrFail($activityId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $result = $this->participantService->getCheckInList($activity, $userId);

            $data = [
                'checked_in' => ClubActivityParticipantResource::collection($result['checked_in']),
                'waiting' => ClubActivityParticipantResource::collection($result['waiting']),
                'summary' => $result['summary'],
            ];

            return ResponseHelper::success($data, 'Lấy danh sách check-in thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }
}
