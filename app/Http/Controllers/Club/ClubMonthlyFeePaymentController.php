<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubMonthlyFeePaymentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessException;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Club\ClubMonthlyFeePaymentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubMonthlyFeePayment;
use App\Services\Club\ClubMonthlyFeePaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubMonthlyFeePaymentController extends Controller
{
    public function __construct(
        protected ClubMonthlyFeePaymentService $paymentService
    ) {
    }

    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|exists:users,id',
            'status' => 'sometimes|in:pending,paid,failed',
            'period' => 'sometimes|date',
        ]);

        $payments = $this->paymentService->getPayments($club, $validated);

        $data = ['payments' => ClubMonthlyFeePaymentResource::collection($payments)];
        $meta = [
            'current_page' => $payments->currentPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
            'last_page' => $payments->lastPage(),
        ];
        return ResponseHelper::success($data, 'Lấy danh sách thanh toán thành công', 200, $meta);
    }

    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        $validated = $request->validate([
            'club_monthly_fee_id' => 'required|exists:club_monthly_fees,id',
            'period' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_code' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $club->monthlyFees()->findOrFail($validated['club_monthly_fee_id']);

        try {
            $payment = $this->paymentService->createPayment($club, $validated, $userId);
            $payment->load(['user', 'monthlyFee', 'walletTransaction']);
            return ResponseHelper::success(new ClubMonthlyFeePaymentResource($payment), 'Thanh toán phí thành công', 201);
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi thanh toán phí', 409);
        }
    }

    public function show($clubId, $paymentId)
    {
        $payment = ClubMonthlyFeePayment::where('club_id', $clubId)
            ->with(['user', 'monthlyFee', 'walletTransaction', 'club'])
            ->findOrFail($paymentId);

        return ResponseHelper::success(new ClubMonthlyFeePaymentResource($payment), 'Lấy thông tin thanh toán thành công');
    }

    public function getMemberPayments(Request $request, $clubId, $memberId)
    {
        $club = Club::findOrFail($clubId);

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => ['sometimes', Rule::enum(ClubMonthlyFeePaymentStatus::class)],
        ]);

        $payments = $this->paymentService->getMemberPayments($club, $memberId, $validated);

        $data = ['payments' => ClubMonthlyFeePaymentResource::collection($payments)];
        $meta = [
            'current_page' => $payments->currentPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
            'last_page' => $payments->lastPage(),
        ];
        return ResponseHelper::success($data, 'Lấy lịch sử thanh toán thành công', 200, $meta);
    }

    public function getStatistics(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $validated = $request->validate([
            'period' => 'sometimes|date',
        ]);

        $stats = $this->paymentService->getStatistics($club, $validated['period'] ?? null);

        return ResponseHelper::success($stats, 'Lấy thống kê thanh toán thành công');
    }
}
