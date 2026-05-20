<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\GetExpenseStatisticsRequest;
use App\Http\Requests\Club\GetExpensesRequest;
use App\Http\Requests\Club\StoreExpenseRequest;
use App\Http\Requests\Club\UpdateExpenseRequest;
use App\Http\Resources\Club\ClubExpenseResource;
use App\Models\Club\Club;
use App\Models\Club\ClubExpense;
use App\Services\Club\ClubExpenseService;

class ClubExpenseController extends Controller
{
    public function __construct(
        protected ClubExpenseService $expenseService
    ) {
    }

    public function index(GetExpensesRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $expenses = $this->expenseService->getExpenses($club, $request->validated());

        $data = ['expenses' => ClubExpenseResource::collection($expenses)];
        $meta = [
            'current_page' => $expenses->currentPage(),
            'per_page' => $expenses->perPage(),
            'total' => $expenses->total(),
            'last_page' => $expenses->lastPage(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách chi phí thành công', 200, $meta);
    }

    public function store(StoreExpenseRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $expense = $this->expenseService->createExpense($club, $request->validated(), $userId);
            $expense->load(['spender', 'walletTransaction']);

            return ResponseHelper::success(new ClubExpenseResource($expense), 'Tạo chi phí thành công', 201);
        } catch (\Exception $e) {
            $isInsufficientFund = str_contains($e->getMessage(), 'Số dư quỹ');
            return ResponseHelper::error($e->getMessage(), $isInsufficientFund ? 422 : 403);
        }
    }

    public function show($clubId, $expenseId)
    {
        $expense = ClubExpense::where('club_id', $clubId)
            ->with(['spender', 'walletTransaction', 'club'])
            ->findOrFail($expenseId);

        return ResponseHelper::success(new ClubExpenseResource($expense), 'Lấy thông tin chi phí thành công');
    }

    public function update(UpdateExpenseRequest $request, $clubId, $expenseId)
    {
        $expense = ClubExpense::where('club_id', $clubId)->findOrFail($expenseId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $expense = $this->expenseService->updateExpense($expense, $request->validated(), $userId);
            $expense->load(['spender', 'walletTransaction']);

            return ResponseHelper::success(new ClubExpenseResource($expense), 'Cập nhật chi phí thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function destroy($clubId, $expenseId)
    {
        $expense = ClubExpense::where('club_id', $clubId)->findOrFail($expenseId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->expenseService->deleteExpense($expense, $userId);
            return ResponseHelper::success('Xóa chi phí thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function getStatistics(GetExpenseStatisticsRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $stats = $this->expenseService->getStatistics($club, $request->validated());

        return ResponseHelper::success($stats, 'Lấy thống kê chi phí thành công');
    }
}
