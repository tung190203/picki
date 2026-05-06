<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Events\SuperAdmin\PaymentConfirmed;
use App\Helpers\ResponseHelper;
use App\Models\Tournament;
use App\Models\TournamentFundCollection;
use App\Models\TournamentFundContribution;
use App\Models\Participant;
use App\Models\TournamentParticipantPayment;
use App\Notifications\TournamentPaymentConfirmedNotification;
use App\Services\ImageOptimizationService;
use App\Services\TournamentFundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $qrUrl = $tournament->qr_code_url; // uses model's getQrCodeUrlAttribute() accessor

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

        // Đảm bảo organizer luôn có payment record STATUS_CONFIRMED
        $feePerPerson = $this->calculateFeePerPerson($tournament);
        foreach ($organizerIds as $orgId) {
            $hasPayment = $allPayments->contains(fn($p) => $p->user_id === $orgId);
            if (!$hasPayment) {
                $existingParticipant = Participant::where('tournament_id', $tournament->id)
                    ->where('user_id', $orgId)
                    ->first();
                if ($existingParticipant && $existingParticipant->is_confirmed) {
                    TournamentParticipantPayment::create([
                        'tournament_id' => $tournament->id,
                        'participant_id' => $existingParticipant->id,
                        'user_id' => $orgId,
                        'amount' => $feePerPerson,
                        'status' => TournamentParticipantPayment::STATUS_CONFIRMED,
                        'paid_at' => now(),
                        'confirmed_at' => now(),
                        'confirmed_by' => Auth::id(),
                        'admin_note' => 'Auto tạo: chủ kèo mặc định đã đóng tiền',
                    ]);
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
                $organizerCount = count($organizerIds);
                $memberParticipantCount = max(0, $participantCount - $organizerCount);
                $totalExpected = $tournament->fee_per_person * $memberParticipantCount;
            }
        }

        $data = [
            'tournament_id' => $tournament->id,
            'payment_config' => [
                'has_fee' => $tournament->has_fee,
                'auto_split_fee' => $tournament->auto_split_fee,
                'fee_amount' => $tournament->fee_amount ?? 0,
                'fee_per_person' => $tournament->fee_per_person,
                'final_fee_per_person' => $tournament->final_fee_per_person,
                'auto_approve' => $tournament->auto_approve,
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

        $qrUrl = $tournament->qr_code_url; // uses model's getQrCodeUrlAttribute() accessor

        $data = [
            'payment' => $payment ? new TournamentParticipantPaymentResource($payment) : null,
            'payment_config' => [
                'has_fee' => $tournament->has_fee,
                'auto_split_fee' => $tournament->auto_split_fee,
                'fee_amount' => $tournament->fee_amount ?? 0,
                'fee_per_person' => $tournament->fee_per_person,
                'final_fee_per_person' => $tournament->final_fee_per_person,
                'auto_approve' => $tournament->auto_approve,
                'auto_payment_created' => $tournament->auto_payment_created,
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
                'final_fee_per_person' => $tournament->final_fee_per_person,
                'auto_approve' => $tournament->auto_approve,
                'auto_payment_created' => $tournament->auto_payment_created,
            ],
        ];

        return ResponseHelper::success($data, 'Lấy thanh toán thành công');
    }

    /**
     * POST /api/tournaments/{id}/payments
     * Thành viên nộp receipt thanh toán
     *
     * Hỗ trợ:
     * - Đóng tiền cho bản thân
     * - Đóng tiền cho guests (khi có guest_ids)
     * - Auto-approve nếu tournament.auto_approve = true
     * - Ngăn thanh toán sớm khi auto_split_fee nhưng chưa lock fee
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

        // Ngăn thanh toán sớm khi auto_split_fee nhưng chưa lock fee
        if ($tournament->auto_split_fee && !$tournament->auto_payment_created) {
            return ResponseHelper::error(
                'Giải đấu đang cài đặt chia tiền tự động, chỉ được thanh toán khi giải đấu đã bắt đầu',
                400
            );
        }

        $validated = $request->validate([
            'receipt_image' => 'nullable',
            'note' => 'nullable|string|max:500',
            'guest_ids' => 'nullable|array',
            'guest_ids.*' => 'integer|exists:participants,id',
        ]);

        // Tính feePerPerson
        $feePerPerson = $this->calculateFeePerPerson($tournament);

        // Handle receipt image
        $receiptImage = $validated['receipt_image'] ?? null;
        if ($request->hasFile('receipt_image')) {
            $receiptImage = $this->imageService->optimize(
                $request->file('receipt_image'),
                'tournament_payments/receipts'
            );
        }

        $guestIds = $validated['guest_ids'] ?? [];

        // Tìm participant của user hiện tại
        $participant = Participant::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        // Validate guest_ids nếu có
        if (!empty($guestIds)) {
            $validGuests = Participant::whereIn('id', $guestIds)
                ->where('guarantor_user_id', $userId)
                ->where('is_guest', true)
                ->whereIn('payment_status', [
                    PaymentStatusEnum::PENDING->value,
                    TournamentParticipantPayment::STATUS_REJECTED,
                ])
                ->pluck('id')
                ->toArray();
            $guestIds = $validGuests;
        }

        DB::beginTransaction();
        try {
            $payments = [];

            if (!empty($guestIds)) {
                // Khi auto_split_fee = true: thanh toán cho cả user + guests trong 1 request
                // Khi auto_split_fee = false: chỉ thanh toán cho guests
                $paymentStatus = $tournament->auto_approve
                    ? TournamentParticipantPayment::STATUS_CONFIRMED
                    : TournamentParticipantPayment::STATUS_PAID;

                if ($tournament->auto_split_fee && $participant) {
                    // Cập nhật payment của chính user
                    $existingPayment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
                        ->where('user_id', $userId)
                        ->first();

                    if ($existingPayment) {
                        if ($existingPayment->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                            DB::rollBack();
                            return ResponseHelper::error('Thanh toán của bạn đã được xác nhận, không thể cập nhật', 400);
                        }
                        $existingPayment->update([
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => $validated['note'] ?? null,
                            'paid_at' => now(),
                            'admin_note' => null,
                            'confirmed_at' => $tournament->auto_approve ? now() : null,
                            'confirmed_by' => $tournament->auto_approve ? $userId : null,
                        ]);
                        $existingPayment->load(['user', 'confirmer']);
                        $payments[] = $existingPayment;
                    } else {
                        $myPayment = TournamentParticipantPayment::create([
                            'tournament_id' => $tournamentId,
                            'participant_id' => $participant->id,
                            'user_id' => $userId,
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => $validated['note'] ?? null,
                            'paid_at' => now(),
                            'confirmed_at' => $tournament->auto_approve ? now() : null,
                            'confirmed_by' => $tournament->auto_approve ? $userId : null,
                        ]);
                        $myPayment->load(['user', 'confirmer']);
                        $payments[] = $myPayment;
                    }

                    if ($tournament->auto_approve) {
                        $participant->update([
                            'is_confirmed' => true,
                            'payment_status' => PaymentStatusEnum::CONFIRMED,
                        ]);
                    }
                }

                // Cập nhật payment của từng guest
                $guests = Participant::whereIn('id', $guestIds)->get();
                foreach ($guests as $guest) {
                    $existingGuestPayment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
                        ->where('participant_id', $guest->id)
                        ->first();

                    if ($existingGuestPayment) {
                        if ($existingGuestPayment->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                            DB::rollBack();
                            return ResponseHelper::error("Thanh toán cho guest {$guest->guest_name} đã được xác nhận", 400);
                        }
                        $existingGuestPayment->update([
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => "Guest {$guest->guest_name}" . ($guest->guest_phone ? " - {$guest->guest_phone}" : ''),
                            'paid_at' => now(),
                            'admin_note' => null,
                            'confirmed_at' => $tournament->auto_approve ? now() : null,
                            'confirmed_by' => $tournament->auto_approve ? $userId : null,
                        ]);
                        $existingGuestPayment->load(['user', 'confirmer']);
                        $payments[] = $existingGuestPayment;
                    } else {
                        $existingGuestPayment = TournamentParticipantPayment::create([
                            'tournament_id' => $tournamentId,
                            'participant_id' => $guest->id,
                            'user_id' => $guest->user_id,
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => "Guest {$guest->guest_name}" . ($guest->guest_phone ? " - {$guest->guest_phone}" : ''),
                            'paid_at' => now(),
                            'confirmed_at' => $tournament->auto_approve ? now() : null,
                            'confirmed_by' => $tournament->auto_approve ? $userId : null,
                        ]);
                        $existingGuestPayment->load(['user', 'confirmer']);
                        $payments[] = $existingGuestPayment;
                    }
                }

                // Cập nhật payment_status cho guests
                $guestPaymentStatus = $tournament->auto_approve
                    ? PaymentStatusEnum::CONFIRMED->value
                    : PaymentStatusEnum::PENDING->value;
                Participant::whereIn('id', $guestIds)
                    ->update(['payment_status' => $guestPaymentStatus]);

                DB::commit();

                // Sync TournamentFundContribution khi auto_approve = true
                if ($tournament->auto_approve && $tournament->tournament_fund_collection_id) {
                    foreach ($payments as $p) {
                        if ($p->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                            TournamentFundContribution::where('tournament_fund_collection_id', $tournament->tournament_fund_collection_id)
                                ->where('user_id', $p->user_id)
                                ->update(['status' => 'confirmed']);
                            PaymentConfirmed::dispatch($tournamentId, $p->id, $p->amount, $p->user_id);
                        }
                    }
                }

                $message = $tournament->auto_approve
                    ? ($tournament->auto_split_fee
                        ? 'Thanh toán cho bản thân và guest thành công, đã được xác nhận'
                        : 'Thanh toán cho guest thành công, đã được xác nhận')
                    : ($tournament->auto_split_fee
                        ? 'Thanh toán cho bản thân và guest thành công, chờ BTC xác nhận'
                        : 'Thanh toán cho guest thành công, chờ BTC xác nhận');

                return ResponseHelper::success(
                    TournamentParticipantPaymentResource::collection(collect($payments)),
                    $message
                );
            }

            // Không có guest_ids: xử lý thanh toán của user
            $existingPayment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
                ->where('user_id', $userId)
                ->first();

            if ($existingPayment) {
                if ($existingPayment->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                    DB::rollBack();
                    return ResponseHelper::error('Thanh toán đã được xác nhận, không thể cập nhật', 400);
                }

                $newStatus = $tournament->auto_approve
                    ? TournamentParticipantPayment::STATUS_CONFIRMED
                    : TournamentParticipantPayment::STATUS_PAID;

                $existingPayment->update([
                    'amount' => $feePerPerson,
                    'status' => $newStatus,
                    'receipt_image' => $receiptImage,
                    'note' => $validated['note'] ?? null,
                    'paid_at' => now(),
                    'admin_note' => null,
                    'confirmed_at' => $tournament->auto_approve ? now() : null,
                    'confirmed_by' => $tournament->auto_approve ? $userId : null,
                ]);

                if ($tournament->auto_approve && $participant) {
                    $participant->update([
                        'is_confirmed' => true,
                        'payment_status' => PaymentStatusEnum::CONFIRMED,
                    ]);
                }

                $payment = $existingPayment;
            } else {
                $newStatus = $tournament->auto_approve
                    ? TournamentParticipantPayment::STATUS_CONFIRMED
                    : TournamentParticipantPayment::STATUS_PAID;

                $payment = TournamentParticipantPayment::create([
                    'tournament_id' => $tournamentId,
                    'participant_id' => $participant?->id,
                    'user_id' => $userId,
                    'amount' => $feePerPerson,
                    'status' => $newStatus,
                    'receipt_image' => $receiptImage,
                    'note' => $validated['note'] ?? null,
                    'paid_at' => now(),
                    'confirmed_at' => $tournament->auto_approve ? now() : null,
                    'confirmed_by' => $tournament->auto_approve ? $userId : null,
                ]);

                if ($tournament->auto_approve && $participant) {
                    $participant->update([
                        'is_confirmed' => true,
                        'payment_status' => PaymentStatusEnum::CONFIRMED,
                    ]);
                }
            }

            DB::commit();

            // Sync TournamentFundContribution khi auto_approve = true
            if ($tournament->auto_approve && $tournament->tournament_fund_collection_id && $payment->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                TournamentFundContribution::where('tournament_fund_collection_id', $tournament->tournament_fund_collection_id)
                    ->where('user_id', $payment->user_id)
                    ->update(['status' => 'confirmed']);
                PaymentConfirmed::dispatch($tournamentId, $payment->id, $payment->amount, $payment->user_id);
                $payment->user->notify(new TournamentPaymentConfirmedNotification($payment));
            }

            $message = $tournament->auto_approve
                ? 'Thanh toán thành công, đã được xác nhận'
                : 'Thanh toán thành công, chờ BTC xác nhận';

            return ResponseHelper::success(
                new TournamentParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                $message
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TournamentPaymentController::store error', [
                'tournament_id' => $tournamentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Tính phí mỗi người dựa trên cài đặt tournament
     */
    protected function calculateFeePerPerson(Tournament $tournament): int
    {
        if (!$tournament->has_fee) {
            return 0;
        }

        if ($tournament->auto_split_fee) {
            if ($tournament->final_fee_per_person !== null) {
                return (int) $tournament->final_fee_per_person;
            }
            $participantCount = $tournament->participants()->where('is_confirmed', true)->count();
            if ($participantCount > 0) {
                return (int) round($tournament->fee_amount / $participantCount);
            }
            return 0;
        }

        return (int) ($tournament->fee_amount ?? 0);
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

        $this->fundService->rejectPayment($payment, $validated['reason']);

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

        $participant = Participant::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        $existingPayment = TournamentParticipantPayment::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        if ($existingPayment) {
            if ($existingPayment->status === TournamentParticipantPayment::STATUS_CONFIRMED) {
                return ResponseHelper::error('Thanh toán đã được xác nhận trước đó', 400);
            }
            $payment = $this->fundService->markPaidManually($tournament, $userId, auth()->user());
        } else {
            // Tạo payment record mới nếu chưa có
            $feePerPerson = $this->calculateFeePerPerson($tournament);
            $payment = TournamentParticipantPayment::create([
                'tournament_id' => $tournamentId,
                'participant_id' => $participant?->id,
                'user_id' => $userId,
                'amount' => $feePerPerson,
                'status' => TournamentParticipantPayment::STATUS_CONFIRMED,
                'paid_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
                'admin_note' => 'BTC đánh dấu đã thanh toán',
            ]);

            // Sync Participant.payment_status
            if ($participant) {
                $participant->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
            }

            PaymentConfirmed::dispatch($tournamentId, $payment->id, $payment->amount, $userId);
        }

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
