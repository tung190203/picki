<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubFundContributionStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Club\ClubFundContributionResource;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Services\Club\ClubFundContributionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubFundContributionController extends Controller
{
    public function __construct(
        protected ClubFundContributionService $contributionService
    ) {
    }

    public function index(Request $request, $clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$collection->club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|exists:users,id',
            'status' => ['sometimes', Rule::enum(ClubFundContributionStatus::class)],
        ]);

        $contributions = $this->contributionService->getContributions($collection, $validated);

        $data = ['contributions' => ClubFundContributionResource::collection($contributions)];
        $meta = [
            'current_page' => $contributions->currentPage(),
            'per_page' => $contributions->perPage(),
            'total' => $contributions->total(),
            'last_page' => $contributions->lastPage(),
        ];
        return ResponseHelper::success($data, 'Lấy danh sách đóng góp thành công', 200, $meta);
    }

    public function store(Request $request, $clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$collection->club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới được nộp biên lai', 403);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg,gif|max:5120',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $contribution = $this->contributionService->submitContribution(
                $collection,
                $userId,
                $request->file('image'),
                $validated['note'] ?? null
            );

            $contribution->load(['user', 'walletTransaction']);
            return ResponseHelper::success(new ClubFundContributionResource($contribution), 'Đã gửi biên lai', 201);
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi gửi biên lai', 422);
        }
    }

    public function markMemberPaid(Request $request, $clubId, $collectionId)
    {
        $collection = ClubFundCollection::where('club_id', $clubId)->findOrFail($collectionId);
        $club = $collection->club;
        $userId = auth()->id();

        if (!$club->canManageFinance($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary/treasurer mới có quyền đánh dấu đã đóng', 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $contribution = $this->contributionService->markMemberPaid(
                $collection,
                (int) $validated['user_id'],
                $userId
            );
            $contribution->load(['user', 'walletTransaction']);
            return ResponseHelper::success(new ClubFundContributionResource($contribution), 'Đã đánh dấu thành viên đã đóng');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi đánh dấu thành viên đã đóng', 422);
        }
    }

    public function show($clubId, $collectionId, $contributionId)
    {
        $contribution = ClubFundContribution::whereHas('fundCollection', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_fund_collection_id', $collectionId)
            ->with(['user', 'walletTransaction', 'fundCollection'])
            ->findOrFail($contributionId);

        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$contribution->fundCollection->club->isMember($userId)) {
            return ResponseHelper::error('Chỉ thành viên CLB mới xem được', 403);
        }

        return ResponseHelper::success(new ClubFundContributionResource($contribution), 'Lấy thông tin đóng góp thành công');
    }

    public function confirm($clubId, $collectionId, $contributionId)
    {
        $contribution = ClubFundContribution::whereHas('fundCollection', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_fund_collection_id', $collectionId)
            ->findOrFail($contributionId);

        $club = $contribution->fundCollection->club;
        $userId = auth()->id();

        if (!$club->canManageFinance($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary/treasurer mới có quyền xác nhận', 403);
        }

        try {
            $contribution = $this->contributionService->confirmContribution($contribution, $userId);
            $contribution->load(['user', 'walletTransaction']);
            return ResponseHelper::success(new ClubFundContributionResource($contribution), 'Đóng góp đã được xác nhận');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi xác nhận đóng góp', 422);
        }
    }

    public function reject(Request $request, $clubId, $collectionId, $contributionId)
    {
        $contribution = ClubFundContribution::whereHas('fundCollection', function ($q) use ($clubId) {
            $q->where('club_id', $clubId);
        })->where('club_fund_collection_id', $collectionId)
            ->findOrFail($contributionId);

        $club = $contribution->fundCollection->club;
        $userId = auth()->id();

        if (!$club->canManageFinance($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary/treasurer mới có quyền từ chối', 403);
        }

        $validated = $request->validate(['reason' => 'required|string']);

        $contribution = $this->contributionService->rejectContribution($contribution, $validated['reason']);
        $contribution->load(['user', 'walletTransaction']);
        return ResponseHelper::success(new ClubFundContributionResource($contribution), 'Đóng góp đã bị từ chối');
    }
}
