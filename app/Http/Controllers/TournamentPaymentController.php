<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Tournament;
use App\Models\TournamentFundCollection;
use App\Models\TournamentParticipantPayment;
use App\Services\ImageOptimizationService;
use App\Services\TournamentFundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TournamentParticipantPaymentResource;

class TournamentPaymentController extends Controller
{
    public function __construct(
        protected TournamentFundService $fundService,
        protected ImageOptimizationService $imageService
    ) {
    }

    protected function authorizeAdmin(Tournament $tournament): ?\Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$tournament->hasOrganizerOrStaff($userId)) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }
        return null;
    }

    /**
     * GET /api/tournaments/{id}/payments
     * Lấy danh sách thanh toán cho admin/BTC
     */
    public function index(int $tournamentId)
    {
        $tournament = Tournament::with([
            'payments.user',
            'payments.confirmer',
            'payments.participant',
            'payments.participant.guarantor',
            'participants.guarantor',
            'staff',
        ])->find($tournamentId);

        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        $allPayments = $tournament->payments()->orderBy('created_at', 'desc')->get();

        $pendingPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_PENDING);
        $paidPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_PAID);
        $confirmedPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_CONFIRMED);
        $rejectedPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_REJECTED);

        $organizerIds = $tournament->staff->filter(fn($s) => $s->role === 1)->pluck('user_id')->toArray();

        $participantCount = $tournament->participants()->where('is_confirmed', true)->count();

        $qrUrl = $tournament->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        // Auto-confirm payments for organizers
        foreach ($pendingPayments as $payment) {
            if (in_array($payment->user_id, $organizerIds)) {
                $this->fundService->confirmPayment($payment, Auth::user());
                $confirmedPayments->push($payment);
            }
        }

        // Auto-confirm payments for guests whose guarantor is an organizer
        foreach ($pendingPayments as $payment) {
            if ($payment->participant && $payment->participant->is_guest) {
                $guarantorId = $payment->participant->guarantor_user_id;
                if ($guarantorId && in_array($guarantorId, $organizerIds)) {
                    $this->fundService->confirmPayment($payment, Auth::user());
                    $confirmedPayments->push($payment);
                }
            }
        }

        // Refresh after auto-confirm
        $allPayments = $tournament->payments()->with([
            'user',
            'confirmer',
            'participant',
            'participant.guarantor',
        ])->orderBy('created_at', 'desc')->get();

        $pendingPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_PENDING);
        $paidPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_PAID);
        $confirmedPayments = $allPayments->filter(fn($p) => $p->status === TournamentParticipantPayment::STATUS_CONFIRMED);

        $totalExpected = 0;
        if ($tournament->has_fee) {
            if ($tournament->auto_split_fee) {
                $totalExpected = $confirmedPayments->sum('amount');
            } else {
                $totalExpected = $tournament->fee_per_person * $participantCount;
            }
        }

        $data = [
            'tournament_id' => $tournament->id,
            'payment_config' => [
                'has_fee' => $tournament->has_fee,
                'auto_split_fee' => $tournament->auto_split_fee,
                'fee_amount' => $tournament->fee_amount ?? 0,
                'fee_per_person' => $tournament->fee_per_person,
                'fee_description' => $tournament->fee_description,
                'qr_code_url' => $qrUrl,
            ],
            'summary' => [
                'total_participants' => $participantCount,
                'total_expected' => $totalExpected,
                'total_collected' => $confirmedPayments->sum('amount'),
                'total_pending' => $pendingPayments->count(),
                'total_awaiting_confirmation' => $paidPayments->count(),
                'total_confirmed' => $confirmedPayments->count(),
                'total_rejected' => $rejectedPayments->count(),
            ],
            'payments' => TournamentParticipantPaymentResource::collection($allPayments),
            'pending_payments' => TournamentParticipantPaymentResource::collection($pendingPayments->values()),
            'awaiting_confirmation_payments' => TournamentParticipantPaymentResource::collection($paidPayments->values()),
            'confirmed_payments' => TournamentParticipantPaymentResource::collection($confirmedPayments->values()),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách thanh toán thành công');
    }

    /**
     * GET /api/tournaments/{id}/my-payment
     * Lấy thanh toán của người dùng hiện tại
     */
    public function myPayment(int $tournamentId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        $payment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        // Check if user is organizer
        $isOrganizer = $tournament->hasOrganizerOrStaff($userId);

        // Auto-confirm for organizers
        if ($payment && $payment->status === TournamentParticipantPayment::STATUS_PENDING && $isOrganizer) {
            $this->fundService->confirmPayment($payment, Auth::user());
            $payment->refresh();
        }

        $qrUrl = $tournament->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        $data = [
            'payment' => $payment ? new TournamentParticipantPaymentResource($payment) : null,
            'payment_config' => [
                'has_fee' => $tournament->has_fee,
                'auto_split_fee' => $tournament->auto_split_fee,
                'fee_amount' => $tournament->fee_amount ?? 0,
                'fee_per_person' => $tournament->fee_per_person,
                'fee_description' => $tournament->fee_description,
                'qr_code_url' => $qrUrl,
            ],
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'has_financial_management' => $tournament->has_financial_management,
                'has_fee' => $tournament->has_fee,
                'fee_amount' => $tournament->fee_amount,
                'qr_code_url' => $qrUrl,
                'fee_description' => $tournament->fee_description,
                'included_in_club_fund' => $tournament->included_in_club_fund,
            ],
        ];

        return ResponseHelper::success($data, 'Lấy thanh toán thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments
     * Thành viên nộp receipt thanh toán
     */
    public function store(Request $request, int $tournamentId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if (!$tournament->has_financial_management) {
            return ResponseHelper::error('Giải đấu không bật quản lý tài chính', 422);
        }

        if (!$tournament->has_fee) {
            return ResponseHelper::error('Giải đấu miễn phí, không cần thanh toán', 422);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'receipt_image' => 'nullable',
            'note' => 'nullable|string|max:500',
        ]);

        // Handle receipt image upload
        $receiptUrl = null;
        if ($request->hasFile('receipt_image')) {
            $receiptUrl = $this->imageService->optimize(
                $request->file('receipt_image'),
                'tournament_payments/receipts'
            );
        } elseif (!empty($validated['receipt_image'])) {
            $receiptUrl = $validated['receipt_image'];
        }

        $payment = $this->fundService->submitPayment(
            $tournament,
            auth()->user(),
            [
                'amount' => $validated['amount'] ?? $tournament->fee_per_person,
                'receipt_image' => $receiptUrl,
                'note' => $validated['note'] ?? null,
            ]
        );

        $payment->load('user');

        return ResponseHelper::success($payment, 'Nộp thanh toán thành công, chờ BTC xác nhận');
    }

    /**
     * POST /api/tournaments/{id}/payments/{pid}/confirm
     * Admin xác nhận thanh toán
     */
    public function confirm(int $tournamentId, int $paymentId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        $payment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            return ResponseHelper::error('Thanh toán không tồn tại', 404);
        }

        if ($payment->isConfirmed()) {
            return ResponseHelper::error('Thanh toán đã được xác nhận trước đó', 422);
        }

        $this->fundService->confirmPayment($payment, auth()->user());

        $payment->load('user', 'confirmer');

        return ResponseHelper::success($payment, 'Xác nhận thanh toán thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments/{pid}/reject
     * Admin từ chối thanh toán
     */
    public function reject(Request $request, int $tournamentId, int $paymentId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            return ResponseHelper::error('Thanh toán không tồn tại', 404);
        }

        $this->fundService->rejectPayment($payment, auth()->user(), $validated['reason']);

        $payment->load('user');

        return ResponseHelper::success($payment, 'Từ chối thanh toán thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments/mark-paid/{uid}
     * Admin đánh dấu thành viên đã thanh toán (không cần receipt)
     */
    public function markPaid(int $tournamentId, int $userId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        $payment = $this->fundService->markPaidManually($tournament, $userId, auth()->user());

        $payment->load('user', 'confirmer');

        return ResponseHelper::success($payment, 'Đánh dấu thanh toán thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments/remind/{uid}
     * Gửi nhắc nhở cho 1 thành viên
     */
    public function remind(int $tournamentId, int $userId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        $this->fundService->remindUser($tournament, $userId);

        return ResponseHelper::success(null, 'Đã gửi nhắc nhở thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments/remind-all
     * Gửi nhắc nhở cho tất cả thành viên chưa thanh toán
     */
    public function remindAll(int $tournamentId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        $reminded = $this->fundService->remindAllPending($tournament);

        return ResponseHelper::success(['count' => count($reminded)], 'Đã gửi nhắc nhở cho ' . count($reminded) . ' thành viên');
    }

    /**
     * GET /api/tournaments/{id}/fund-collection
     * Lấy thông tin fund collection
     */
    public function fundCollection(int $tournamentId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        $collection = null;
        if ($tournament->tournament_fund_collection_id) {
            $collection = TournamentFundCollection::with('members', 'contributions')
                ->find($tournament->tournament_fund_collection_id);
        }

        return ResponseHelper::success([
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'has_financial_management' => $tournament->has_financial_management,
                'use_club_fund' => $tournament->use_club_fund,
                'included_in_club_fund' => $tournament->included_in_club_fund,
                'club_fund_collection_id' => $tournament->club_fund_collection_id,
                'tournament_fund_collection_id' => $tournament->tournament_fund_collection_id,
            ],
            'collection' => $collection,
        ], 'Lấy thông tin quỹ thành công');
    }
}
