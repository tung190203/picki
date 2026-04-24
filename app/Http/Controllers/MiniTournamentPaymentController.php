<?php

namespace App\Http\Controllers;

use App\Enums\ClubFundContributionStatus;
use App\Enums\PaymentStatusEnum;
use App\Events\SuperAdmin\PaymentConfirmed;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniParticipantPaymentResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\ClubFundContribution;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;

use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentReminderNotification;
use App\Services\Club\ClubFundContributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MiniTournamentPaymentController extends Controller
{
    public function __construct(
        protected ClubFundContributionService $fundContributionService,
    ) {
    }

    /**
     * Lấy chi tiết khoản thu phí của kèo
     * API: GET /api/mini-tournaments/{id}/payments
     */
    public function index(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::with([
            'competitionLocation',
            'sport',
        ])->findOrFail($miniTournamentId);

        // Load payments với user, confirmer và participant (để lấy guarantor nếu là guest)
        $payments = MiniParticipantPayment::with(['user', 'confirmer', 'participant'])
            ->where('mini_tournament_id', $miniTournamentId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Group payments by status
        $pendingPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_PENDING);
        $paidPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_PAID);
        $confirmedPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_CONFIRMED);
        $rejectedPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_REJECTED);

        // Tính tổng tiền (chỉ đếm participants đã confirmed)
        $participantCount = $miniTournament->participants()->where('is_confirmed', true)->count();

        // Tính số tiền mỗi người phải đóng
        $feePerPerson = 0;
        if ($miniTournament->has_fee) {
            if ($miniTournament->auto_split_fee) {
                // Nếu đã lock final_fee_per_person, dùng giá trị đó
                if ($miniTournament->final_fee_per_person !== null) {
                    $feePerPerson = $miniTournament->final_fee_per_person;
                } else {
                    // Chia tự động: tổng tiền / số người đã confirmed
                $participantCount = $miniTournament->participants()->where('is_confirmed', true)->count();
                $feePerPerson = $participantCount > 0 ? round($miniTournament->fee_amount / $participantCount) : 0;
                }
            } else {
                // Tiền cố định mỗi người
                $feePerPerson = $miniTournament->fee_amount;
            }
        }

        $qrUrl = $miniTournament->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        $data = [
            'mini_tournament_id' => $miniTournament->id,
            'payment_config' => [
                'has_fee' => $miniTournament->has_fee,
                'auto_split_fee' => $miniTournament->auto_split_fee,
                'fee_amount' => $miniTournament->fee_amount,
                'fee_per_person' => $feePerPerson,
                'fee_description' => $miniTournament->fee_description,
                'qr_code_url' => $qrUrl,
                'payment_account_id' => $miniTournament->payment_account_id,
            ],
            'summary' => [
                'total_participants' => $participantCount,
                'total_expected' => $feePerPerson * $participantCount,
                'total_collected' => $confirmedPayments->sum('amount'),
                'total_pending' => $pendingPayments->count(),
                'total_awaiting_confirmation' => $paidPayments->count(),
                'total_confirmed' => $confirmedPayments->count(),
                'total_rejected' => $rejectedPayments->count(),
            ],
            'payments' => MiniParticipantPaymentResource::collection($payments),
            'pending_payments' => MiniParticipantPaymentResource::collection($pendingPayments->values()),
            'awaiting_confirmation_payments' => MiniParticipantPaymentResource::collection($paidPayments->values()),
            'confirmed_payments' => MiniParticipantPaymentResource::collection($confirmedPayments->values()),
        ];

        return ResponseHelper::success($data, 'Lấy thông tin thanh toán thành công');
    }

    /**
     * API đóng phí kèo
     * API: POST /api/mini-tournaments/{id}/pay
     * Body: receipt_image (bắt buộc), note (không bắt buộc)
     *
     * BE tự động xử lý:
     * - Nếu user đã là thành viên: thanh toán cho participant hiện tại
     * - Nếu user chưa là thành viên:
     *   - Nếu auto_approve = true: tạo participant mới + thanh toán
     *   - Nếu auto_approve = false: trả lỗi
     */
    public function pay(Request $request, $miniTournamentId)
    {
        $data = $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'note' => 'nullable|string|max:500',
            'guest_ids' => 'nullable|array',
            'guest_ids.*' => 'integer|exists:mini_participants,id',
        ]);

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Nếu kèo chia tiền tự động thì chỉ được thanh toán khi đã chia tiền tự động (kèo bắt đầu)
        if ($miniTournament->auto_split_fee && !$miniTournament->auto_payment_created) {
            return ResponseHelper::error(
                'Kèo đang cài đặt chia tiền tự động, chỉ được thanh toán khi kèo đã bắt đầu',
                400
            );
        }

        // Kiểm tra kèo có thu phí không
        if (!$miniTournament->has_fee) {
            return ResponseHelper::error('Kèo này không thu phí tham gia', 400);
        }

        // Kèo use_club_fund = true: CLB chi tiền, không thu phí từ member
        if ($miniTournament->use_club_fund) {
            return ResponseHelper::error('Kèo này CLB chi tiền, bạn không cần thanh toán', 400);
        }

        $userId = Auth::id();

        // BE tự động tìm hoặc tạo participant
        $participant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('user_id', $userId)
            ->first();

        // Nếu chưa là thành viên, tạo participant mới
        if (!$participant) {
            // Kiểm tra xem kèo có bật auto_approve không
            if (!$miniTournament->auto_approve) {
                return ResponseHelper::error(
                    'Bạn chưa là thành viên của kèo này. Vui lòng tham gia kèo trước hoặc chủ kèo phải bật chế độ tự động duyệt',
                    400
                );
            }

            // Calculate payment_status for new participant
            $paymentStatus = PaymentStatusEnum::CONFIRMED;
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
                $paymentStatus = PaymentStatusEnum::PENDING;
            }

            // Tạo participant mới với auto_approve
            $participant = MiniParticipant::create([
                'mini_tournament_id' => $miniTournamentId,
                'user_id' => $userId,
                'is_confirmed' => true, // Tự động duyệt
                'is_invited' => false,
                'payment_status' => $paymentStatus,
                'joined_at' => now(),
            ]);
        }

        // Tính số tiền phải đóng
        $participantCount = $miniTournament->participants()->count();
        $feePerPerson = 0;

        if ($miniTournament->auto_split_fee) {
            // Nếu đã lock final_fee_per_person, dùng giá trị đó
            if ($miniTournament->final_fee_per_person !== null) {
                $feePerPerson = $miniTournament->final_fee_per_person;
            } else {
                // Chia tự động: tổng tiền / số người
                $feePerPerson = $participantCount > 0 ? round($miniTournament->fee_amount / $participantCount) : 0;
            }
        } else {
            // Tiền cố định mỗi người
            $feePerPerson = $miniTournament->fee_amount;
        }

        // Tính thêm tiền cho guest_ids nếu có
        $guestIds = $data['guest_ids'] ?? [];
        if (!empty($guestIds)) {
            // Validate: chỉ được đóng tiền cho guest mà user này bảo lãnh
            $validGuests = MiniParticipant::whereIn('id', $guestIds)
                ->where('guarantor_user_id', $userId)
                ->where('is_guest', true)
                ->whereIn('payment_status', [
                    PaymentStatusEnum::PENDING->value,
                    MiniParticipantPayment::STATUS_REJECTED,
                ])
                ->pluck('id')
                ->toArray();

            $guestIds = $validGuests;
        }

        // Kiểm tra xem đã có payment record chưa
        $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $miniTournamentId)
            ->where('participant_id', $participant->id)
            ->first();

        $receiptImage = $data['receipt_image'];
        if ($receiptImage) {
            $path = Storage::disk('public')->put('mini_tournament_payments', $receiptImage);
            $receiptImage = asset('storage/' . $path);
        }

        DB::beginTransaction();
        try {
            $payments = [];

            if (!empty($guestIds)) {
                // Khi auto_split_fee = true: thanh toán cho cả user + guests trong 1 request
                // Khi auto_split_fee = false: chỉ thanh toán cho guests
                $paymentStatus = $miniTournament->auto_approve
                    ? MiniParticipantPayment::STATUS_CONFIRMED
                    : MiniParticipantPayment::STATUS_PAID;

                if ($miniTournament->auto_split_fee) {
                    // Cập nhật payment của chính user
                    $myPayment = null;
                    if ($existingPayment) {
                        if (in_array($existingPayment->status, [MiniParticipantPayment::STATUS_CONFIRMED])) {
                            DB::rollBack();
                            return ResponseHelper::error('Thanh toán của bạn đã được xác nhận, không thể cập nhật', 400);
                        }

                        $existingPayment->update([
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => $data['note'] ?? null,
                            'paid_at' => now(),
                            'admin_note' => null,
                            'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                            'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                        ]);
                        $existingPayment->load(['user', 'confirmer']);
                        $payments[] = $existingPayment;
                        $myPayment = $existingPayment;
                    } else {
                        $myPayment = MiniParticipantPayment::create([
                            'mini_tournament_id' => $miniTournamentId,
                            'participant_id' => $participant->id,
                            'user_id' => $userId,
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => $data['note'] ?? null,
                            'paid_at' => now(),
                            'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                            'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                        ]);
                        $myPayment->load(['user', 'confirmer']);
                        $payments[] = $myPayment;
                    }

                    if ($miniTournament->auto_approve && $myPayment?->user) {
                        $participant->update(['is_confirmed' => true, 'payment_status' => PaymentStatusEnum::CONFIRMED]);
                        $myPayment->user->notify(new PaymentConfirmedNotification($myPayment));
                    }
                }

                // Cập nhật payment của từng guest
                $guests = MiniParticipant::whereIn('id', $guestIds)->get();
                foreach ($guests as $guest) {
                    $existingGuestPayment = MiniParticipantPayment::where('mini_tournament_id', $miniTournamentId)
                        ->where('participant_id', $guest->id)
                        ->first();

                    if ($existingGuestPayment) {
                        if ($existingGuestPayment->status === MiniParticipantPayment::STATUS_CONFIRMED) {
                            DB::rollBack();
                            return ResponseHelper::error("Thanh toán cho guest {$guest->guest_name} đã được xác nhận", 400);
                        }

                        $existingGuestPayment->update([
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => $data['note'] ?? null,
                            'paid_at' => now(),
                            'admin_note' => null,
                            'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                            'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                        ]);
                        $existingGuestPayment->load(['user', 'confirmer']);
                        $payments[] = $existingGuestPayment;
                    } else {
                        // Tạo payment record mới cho guest (user_id có thể null nếu guest không có phone)
                        $existingGuestPayment = MiniParticipantPayment::create([
                            'mini_tournament_id' => $miniTournamentId,
                            'participant_id' => $guest->id,
                            'user_id' => $guest->user_id,
                            'amount' => $feePerPerson,
                            'status' => $paymentStatus,
                            'receipt_image' => $receiptImage,
                            'note' => "Guest {$guest->guest_name}" . ($guest->guest_phone ? " - {$guest->guest_phone}" : ''),
                            'paid_at' => now(),
                            'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                            'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                        ]);
                        $existingGuestPayment->load(['user', 'confirmer']);
                        $payments[] = $existingGuestPayment;
                    }
                }

                $guestPaymentStatus = $miniTournament->auto_approve
                    ? PaymentStatusEnum::CONFIRMED->value
                    : PaymentStatusEnum::PENDING->value;
                MiniParticipant::whereIn('id', $guestIds)
                    ->update(['payment_status' => $guestPaymentStatus]);

                DB::commit();

                // === Sync ClubFundContribution cho kèo CLB ===
                $this->syncClubFundContributionForTournament($miniTournament, $participant->user_id, Auth::id(), $receiptImage);

                $message = $miniTournament->auto_approve
                    ? ($miniTournament->auto_split_fee
                        ? 'Thanh toán cho bản thân và guest thành công, đã được xác nhận'
                        : 'Thanh toán cho guest thành công, đã được xác nhận')
                    : ($miniTournament->auto_split_fee
                        ? 'Thanh toán cho bản thân và guest thành công, chờ chủ kèo xác nhận'
                        : 'Thanh toán cho guest thành công, chờ chủ kèo xác nhận');

                return ResponseHelper::success(
                    MiniParticipantPaymentResource::collection(collect($payments)),
                    $message,
                    200
                );
            }

            // Không có guest_ids: xử lý thanh toán của user
            if ($existingPayment) {
                if (in_array($existingPayment->status, [MiniParticipantPayment::STATUS_CONFIRMED])) {
                    DB::rollBack();
                    return ResponseHelper::error('Thanh toán đã được xác nhận, không thể cập nhật', 400);
                }

                $newStatus = $miniTournament->auto_approve
                    ? MiniParticipantPayment::STATUS_CONFIRMED
                    : MiniParticipantPayment::STATUS_PAID;

                $existingPayment->update([
                    'amount' => $feePerPerson,
                    'status' => $newStatus,
                    'receipt_image' => $receiptImage,
                    'note' => $data['note'] ?? null,
                    'paid_at' => now(),
                    'admin_note' => null,
                    'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                    'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                ]);

                if ($miniTournament->auto_approve) {
                    $participant->update([
                        'is_confirmed' => true,
                        'payment_status' => PaymentStatusEnum::CONFIRMED,
                    ]);
                    $existingPayment->user?->notify(new PaymentConfirmedNotification($existingPayment));
                }

                $payment = $existingPayment;
            } else {
                $newStatus = $miniTournament->auto_approve
                    ? MiniParticipantPayment::STATUS_CONFIRMED
                    : MiniParticipantPayment::STATUS_PAID;

                $payment = MiniParticipantPayment::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'participant_id' => $participant->id,
                    'user_id' => $userId,
                    'amount' => $feePerPerson,
                    'status' => $newStatus,
                    'receipt_image' => $receiptImage,
                    'note' => $data['note'] ?? null,
                    'paid_at' => now(),
                    'confirmed_at' => $miniTournament->auto_approve ? now() : null,
                    'confirmed_by' => $miniTournament->auto_approve ? $userId : null,
                ]);

                if ($miniTournament->auto_approve) {
                    $participant->update([
                        'is_confirmed' => true,
                        'payment_status' => PaymentStatusEnum::CONFIRMED,
                    ]);
                    $payment->user?->notify(new PaymentConfirmedNotification($payment));
                }
                }

            DB::commit();

                // === Sync ClubFundContribution cho kèo CLB ===
                $this->syncClubFundContributionForTournament($miniTournament, $userId, Auth::id(), $receiptImage);

            $message = $miniTournament->auto_approve
                ? 'Thanh toán thành công, đã được xác nhận'
                : 'Thanh toán thành công, chờ chủ kèo xác nhận';

            return ResponseHelper::success(
                new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                $message,
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Xác nhận hoặc từ chối thanh toán của thành viên
     * API: POST /api/mini-tournaments/{id}/payments/{paymentId}/confirm
     * API: POST /api/mini-tournaments/{id}/payments/{paymentId}/reject
     */
    public function confirm(Request $request, $miniTournamentId, $participantId)
    {
        return $this->processConfirmation($request, $miniTournamentId, $participantId, true);
    }

    public function reject(Request $request, $miniTournamentId, $participantId)
    {
        return $this->processConfirmation($request, $miniTournamentId, $participantId, false);
    }

    public function markPaid(Request $request, $miniTournamentId, $participantId)
    {
        $participant = MiniParticipant::where('id', $participantId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();
        if (!$participant) {
            return ResponseHelper::error('Không tìm thấy thành viên trong kèo đấu này', 404);
        }

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu thanh toán thành công', 403);
        }

        // Chỉ cần payment_status CHƯA CONFIRMED là được. Không cần chờ participant chấp nhận lời mời hay có payment record.
        if ($participant->payment_status === PaymentStatusEnum::CONFIRMED) {
            return ResponseHelper::error('Thanh toán đã được xác nhận trước đó', 400);
        }

        $receiptImage = null;
        if ($request->hasFile('receipt_image')) {
            $receiptImage = $request->file('receipt_image')->store('receipts', 'public');
            $receiptImage = asset('storage/' . $receiptImage);
        }

        $note = $request->input('note');

        DB::beginTransaction();
        try {
            $payment = MiniParticipantPayment::where('participant_id', $participantId)
                ->where('mini_tournament_id', $miniTournamentId)
                ->first();

            if ($payment) {
                // Cập nhật payment record có sẵn
                if ($payment->status === MiniParticipantPayment::STATUS_CONFIRMED) {
                    DB::rollBack();
                    return ResponseHelper::error('Thanh toán đã được xác nhận trước đó', 400);
                }

                $payment->update([
                    'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                    'paid_at' => now(),
                    'confirmed_at' => now(),
                    'confirmed_by' => Auth::id(),
                    'receipt_image' => $receiptImage ?? $payment->receipt_image,
                    'note' => $note ?? $payment->note,
                ]);
            } else {
                // Chưa có payment record — tạo mới
                $feePerPerson = 0;
                if ($miniTournament->has_fee) {
                    if ($miniTournament->auto_split_fee) {
                        $feePerPerson = $miniTournament->auto_split_fee
                            ? ($miniTournament->final_fee_per_person ?? $miniTournament->fee_amount)
                            : $miniTournament->fee_amount;
                    } else {
                        $feePerPerson = $miniTournament->fee_amount;
                    }
                }

                $payment = MiniParticipantPayment::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'participant_id' => $participantId,
                    'user_id' => $participant->user_id,
                    'amount' => $feePerPerson,
                    'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                    'receipt_image' => $receiptImage,
                    'note' => $note,
                    'paid_at' => now(),
                    'confirmed_at' => now(),
                    'confirmed_by' => Auth::id(),
                ]);
            }

            // Cập nhật payment_status của participant
            $participant->confirmPayment();

            // === Sync ClubFundContribution + tạo ClubWalletTransaction IN cho kèo CLB ===
            // Dùng $payment->receipt_image vì đã được update ở trên (dòng 488: $receiptImage ?? $payment->receipt_image)
            $this->syncClubFundContributionForTournament($miniTournament, $participant->user_id, Auth::id(), $payment->receipt_image);

            $payment->load('user');
            if ($payment->user) {
                $payment->user->notify(new PaymentConfirmedNotification($payment));
            }

            PaymentConfirmed::dispatch(
                $miniTournamentId,
                $payment->id,
                $payment->amount,
                $participant->user_id
            );

            DB::commit();

            return ResponseHelper::success(
                new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                'Đã đánh dấu thanh toán và xác nhận thành công'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function processConfirmation(Request $request, $miniTournamentId, $participantId, bool $isConfirm)
    {
        $participant = MiniParticipant::where('id', $participantId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();
        if (!$participant) {
            return ResponseHelper::error('Không tìm thấy thành viên trong kèo đấu này', 404);
        }

        $payment = MiniParticipantPayment::where('participant_id', $participantId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();
        if (!$payment) {
            return ResponseHelper::error('Không tìm thấy thanh toán của thành viên này', 404);
        }

        $data = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xác nhận thanh toán', 403);
        }

        if ($payment->status !== MiniParticipantPayment::STATUS_PAID) {
            return ResponseHelper::error('Thanh toán đang ở trạng thái không thể xác nhận', 400);
        }

        DB::beginTransaction();
        try {
            $newStatus = $isConfirm ? MiniParticipantPayment::STATUS_CONFIRMED : MiniParticipantPayment::STATUS_REJECTED;

            $payment->update([
                'status' => $newStatus,
                'admin_note' => $data['admin_note'] ?? null,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            if ($isConfirm) {
                $participant->confirmPayment();

                PaymentConfirmed::dispatch(
                    $miniTournamentId,
                    $payment->id,
                    $payment->amount,
                    $payment->user_id
                );

                if (!empty($payment->guest_ids)) {
                    MiniParticipant::whereIn('id', $payment->guest_ids)
                        ->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
                }

                // === Xác nhận ClubFundContribution + tạo ClubWalletTransaction IN ===
                $collection = $miniTournament->fundCollection;
                if ($collection && $collection->isActive()) {
                    $existing = ClubFundContribution::where('club_fund_collection_id', $collection->id)
                        ->where('user_id', $participant->user_id)
                        ->first();
                    if ($existing) {
                        $this->fundContributionService->confirmContribution($existing, Auth::id());
                    } else {
                        // Edge case: chưa có contribution (e.g. user không trong assignedMembers)
                        // Tạo mới Confirmed + wallet tx
                        $this->fundContributionService->markMemberPaid(
                            $collection,
                            $participant->user_id,
                            Auth::id(),
                            $payment->receipt_image
                        );
                    }
                }
            }

            $payment->load('user');
            if ($payment->user) {
                if ($isConfirm) {
                    $payment->user->notify(new PaymentConfirmedNotification($payment));
                } else {
                    $payment->user->notify(new PaymentRejectedNotification($payment));
                }
            }

            DB::commit();

            $message = $isConfirm ? 'Xác nhận thanh toán thành công' : 'Từ chối thanh toán thành công';

            return ResponseHelper::success(
                new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                $message
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    /**
     * Nhắc thành viên đóng phí
     * API: POST /api/mini-tournaments/{id}/payments/remind/{participantId}
     */
    public function remind(Request $request, $miniTournamentId, $participantId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Kiểm tra quyền organizer
        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền nhắc nhở thanh toán', 403);
        }

        // Kiểm tra kèo có thu phí không
        if (!$miniTournament->has_fee) {
            return ResponseHelper::error('Kèo này không thu phí tham gia', 400);
        }

        $participant = MiniParticipant::where('id', $participantId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Không tìm thấy thành viên', 404);
        }

        // Kiểm tra xem đã thanh toán chưa
        $payment = MiniParticipantPayment::where('mini_tournament_id', $miniTournamentId)
            ->where('participant_id', $participantId)
            ->where('status', MiniParticipantPayment::STATUS_CONFIRMED)
            ->first();

        if ($payment) {
            return ResponseHelper::error('Thành viên này đã thanh toán rồi', 400);
        }

        // Gửi notification nhắc nhở
        $participant->load('user');
        if ($participant->user) {
            $participant->user->notify(new PaymentReminderNotification($miniTournament, $participant));
        }

        return ResponseHelper::success(null, 'Đã gửi nhắc nhở thanh toán cho thành viên');
    }

    /**
     * Nhắc tất cả thành viên chưa thanh toán
     * API: POST /api/mini-tournaments/{id}/payments/remind-all
     */
    public function remindAll(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Kiểm tra quyền organizer
        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền nhắc nhở thanh toán', 403);
        }

        // Kiểm tra kèo có thu phí không
        if (!$miniTournament->has_fee) {
            return ResponseHelper::error('Kèo này không thu phí tham gia', 400);
        }

        // Lấy danh sách thành viên chưa thanh toán
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('is_confirmed', true)
            ->get();

        $remindedCount = 0;

        foreach ($participants as $participant) {
            // Kiểm tra đã thanh toán chưa
            $payment = MiniParticipantPayment::where('mini_tournament_id', $miniTournamentId)
                ->where('participant_id', $participant->id)
                ->where('status', MiniParticipantPayment::STATUS_CONFIRMED)
                ->first();

            if (!$payment && $participant->user) {
                $participant->user->notify(new PaymentReminderNotification($miniTournament, $participant));
                $remindedCount++;
            }
        }

        return ResponseHelper::success([
            'reminded_count' => $remindedCount,
        ], "Đã gửi nhắc nhở cho {$remindedCount} thành viên");
    }

    /**
     * Lấy trạng thái thanh toán của user hiện tại
     * API: POST /api/mini-tournaments/{id}/my-payment
     *
     * Hỗ trợ cả user chưa là thành viên:
     * - Nếu user đã là thành viên: lấy payment của participant
     * - Nếu user chưa là thành viên: trả về thông tin thanh toán (chưa có payment)
     */
    public function myPayment(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        $userId = Auth::id();

        // Tìm participant của user hiện tại
        $participant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('user_id', $userId)
            ->first();

        // Lấy payment (nếu có participant)
        $payment = null;
        if ($participant) {
            $payment = MiniParticipantPayment::with(['user', 'confirmer'])
                ->where('mini_tournament_id', $miniTournamentId)
                ->where('participant_id', $participant->id)
                ->first();
        }

        // === POST: User nộp tiền ===
        if ($request->isMethod('POST')) {
            $data = $request->validate([
                'admin_note' => 'nullable|string|max:500',
            ]);

            if (!$miniTournament->has_fee) {
                return ResponseHelper::error('Kèo đấu này không thu phí', 400);
            }

            if ($miniTournament->use_club_fund) {
                return ResponseHelper::error('Kèo này CLB chi tiền, bạn không cần thanh toán', 400);
            }

            // === Tạo participant nếu chưa tham gia ===
            if (!$participant) {
                if ($miniTournament->max_players) {
                    $confirmedCount = $miniTournament->participants()
                        ->where('is_confirmed', true)
                        ->count();
                    if ($confirmedCount >= $miniTournament->max_players) {
                        return ResponseHelper::error('Kèo đã đủ số lượng người chơi.', 400);
                    }
                }

                $participant = MiniParticipant::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'user_id' => $userId,
                    'is_confirmed' => false,
                    'is_invited' => false,
                    'payment_status' => PaymentStatusEnum::PENDING,
                ]);
            }

            // === Tạo payment record nếu chưa có ===
            if (!$payment) {
                $payment = MiniParticipantPayment::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'participant_id' => $participant->id,
                    'user_id' => $userId,
                    'amount' => $miniTournament->fee_amount,
                    'status' => MiniParticipantPayment::STATUS_PENDING,
                ]);
            }

            // === Kiểm tra trạng thái payment hiện tại ===
            if (!in_array($payment->status, [MiniParticipantPayment::STATUS_PENDING, MiniParticipantPayment::STATUS_REJECTED])) {
                return ResponseHelper::error('Thanh toán đang ở trạng thái không thể cập nhật', 400);
            }

            DB::beginTransaction();
            try {
                // Nếu auto_approve = true: tự động confirm
                if ($miniTournament->auto_approve) {
                    $payment->update([
                        'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                        'paid_at' => now(),
                        'confirmed_at' => now(),
                        'confirmed_by' => $userId,
                    ]);

                    $participant->update([
                        'is_confirmed' => true,
                        'payment_status' => PaymentStatusEnum::CONFIRMED,
                    ]);

                    if ($payment->user) {
                        $payment->user->notify(new PaymentConfirmedNotification($payment));
                    }

                    // === Sync ClubFundContribution cho kèo CLB ===
                    $this->syncClubFundContributionForTournament($miniTournament, $userId, $userId, $payment->receipt_image);

                    DB::commit();

                    return ResponseHelper::success(
                        new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                        'Đã xác nhận thanh toán thành công'
                    );
                }

                // auto_approve = false: chỉ đánh dấu paid
                $payment->update([
                    'status' => MiniParticipantPayment::STATUS_PAID,
                    'paid_at' => now(),
                ]);

                DB::commit();

                return ResponseHelper::success(
                    new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                    'Đã đánh dấu đã thanh toán, chờ xác nhận từ admin'
                );
            } catch (\Throwable $e) {
                DB::rollBack();
                return ResponseHelper::error($e->getMessage());
            }
        }

        // Tính số tiền phải đóng
        $feePerPerson = 0;
        if ($miniTournament->has_fee) {
            $participantCount = $miniTournament->participants()->count();
            if ($miniTournament->auto_split_fee) {
                if ($miniTournament->final_fee_per_person !== null) {
                    $feePerPerson = $miniTournament->final_fee_per_person;
                } else {
                    $feePerPerson = $participantCount > 0 ? round($miniTournament->fee_amount / $participantCount) : 0;
                }
            } else {
                $feePerPerson = $miniTournament->fee_amount;
            }
        }

        $qrUrl = $miniTournament->qr_code_url;
        if ($qrUrl && !str_starts_with($qrUrl, 'http')) {
            $qrUrl = asset('storage/' . ltrim($qrUrl, '/'));
        }

        $paymentData = $payment
            ? (new MiniParticipantPaymentResource($payment))->toArray($request)
            : [];

        $data = array_merge([
            'mini_tournament_id' => $miniTournament->id,
            'participant_id' => $participant?->id,
            'has_fee' => (bool) $miniTournament->has_fee,
            'auto_split_fee' => (bool) $miniTournament->auto_split_fee,
            'auto_payment_created' => (bool) $miniTournament->auto_payment_created,
            'auto_approve' => (bool) $miniTournament->auto_approve,
            'fee_amount' => (int) ($miniTournament->fee_amount ?? 0),
            'fee_per_person' => (int) ($feePerPerson ?? 0),
            'fee_description' => $miniTournament->fee_description,
            'qr_code_url' => $qrUrl,
        ], $paymentData);

        return ResponseHelper::success($data, 'Lấy thông tin thanh toán thành công');
    }

    /**
     * Sync ClubFundContribution khi thanh toán kèo CLB.
     * Tạo/update ClubFundContribution CONFIRMED + ClubWalletTransaction IN nếu use_club_fund=true.
     * Đồng bộ receipt_url từ MiniParticipantPayment sang ClubFundContribution.
     *
     * @param  MiniTournament  $tournament
     * @param  int  $userId
     * @param  int  $confirmerId
     * @param  string|null  $receiptUrl
     */
    private function syncClubFundContributionForTournament(
        MiniTournament $tournament,
        int $userId,
        int $confirmerId,
        ?string $receiptUrl = null,
    ): void {
        // Chỉ sync khi kèo thu phí VÀ có fundCollection được gắn
        // (collection riêng để thu quỹ cho kèo này)
        if (!$tournament->has_fee) {
            return;
        }

        $collection = $tournament->fundCollection;
        if (!$collection || !$collection->isActive()) {
            return;
        }

        try {
            $existing = ClubFundContribution::where('club_fund_collection_id', $collection->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                if ($existing->status === ClubFundContributionStatus::Pending) {
                    // Đã có PENDING → xác nhận (tạo wallet tx)
                    $this->fundContributionService->confirmContribution($existing, $confirmerId);
                }
                // Nếu đã Confirmed → không làm gì
            } else {
                // Chưa có contribution → tạo mới PENDING (user nộp biên lai, chờ duyệt)
                // KHÔNG tạo wallet tx ở đây → chờ organizer confirm
                ClubFundContribution::create([
                    'club_fund_collection_id' => $collection->id,
                    'user_id' => $userId,
                    'amount' => $tournament->fee_amount,
                    'receipt_url' => $receiptUrl,
                    'note' => 'Nộp tiền kèo CLB - chờ xác nhận',
                    'status' => ClubFundContributionStatus::Pending,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('MiniTournamentPaymentController: Failed to sync fund contribution', [
                'tournament_id' => $tournament->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
