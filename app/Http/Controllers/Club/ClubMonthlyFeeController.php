<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Exceptions\BusinessException;
use App\Http\Resources\Club\ClubMonthlyFeeResource;
use App\Models\Club\Club;
use App\Models\Club\ClubMonthlyFee;
use App\Services\Club\ClubMonthlyFeeService;
use Illuminate\Http\Request;

class ClubMonthlyFeeController extends Controller
{
    public function __construct(
        protected ClubMonthlyFeeService $feeService
    ) {
    }

    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
        ]);

        $fees = $this->feeService->getFees($club, $validated);

        return ResponseHelper::success(ClubMonthlyFeeResource::collection($fees), 'Lấy danh sách cấu hình phí thành công');
    }

    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManageFinance($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary/treasurer mới có quyền tạo cấu hình phí', 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|max:3',
            'due_day' => 'required|integer|min:1|max:31',
            'is_active' => 'sometimes|boolean',
        ]);

        $fee = $this->feeService->createFee($club, $validated);

        return ResponseHelper::success(new ClubMonthlyFeeResource($fee), 'Tạo cấu hình phí thành công', 201);
    }

    public function show($clubId, $feeId)
    {
        $fee = ClubMonthlyFee::where('club_id', $clubId)->findOrFail($feeId);
        return ResponseHelper::success(new ClubMonthlyFeeResource($fee), 'Lấy thông tin cấu hình phí thành công');
    }

    public function update(Request $request, $clubId, $feeId)
    {
        $fee = ClubMonthlyFee::where('club_id', $clubId)->findOrFail($feeId);
        $userId = auth()->id();

        $club = $fee->club;
        if (!$club->canManageFinance($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary/treasurer mới có quyền cập nhật', 403);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'sometimes|string|max:3',
            'due_day' => 'sometimes|integer|min:1|max:31',
            'is_active' => 'sometimes|boolean',
        ]);

        $fee = $this->feeService->updateFee($fee, $validated);
        return ResponseHelper::success(new ClubMonthlyFeeResource($fee->fresh()), 'Cập nhật cấu hình phí thành công');
    }

    public function destroy($clubId, $feeId)
    {
        $fee = ClubMonthlyFee::where('club_id', $clubId)->findOrFail($feeId);
        $userId = auth()->id();

        $club = $fee->club;
        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xóa', 403);
        }

        try {
            $this->feeService->deleteFee($fee);
            return ResponseHelper::success('Xóa cấu hình phí thành công');
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi xóa cấu hình phí', 422);
        }
    }
}
