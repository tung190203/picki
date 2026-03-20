<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniParticipantPaymentResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\MiniMatch;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MiniTournamentPaymentController extends Controller
{
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

        // Load payments với user relationships
        $payments = MiniParticipantPayment::with(['user', 'confirmer'])
            ->where('mini_tournament_id', $miniTournamentId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Group payments by status
        $pendingPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_PENDING);
        $paidPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_PAID);
        $confirmedPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_CONFIRMED);
        $rejectedPayments = $payments->filter(fn($p) => $p->status === MiniParticipantPayment::STATUS_REJECTED);

        // Tính tổng tiền
        $participantCount = $miniTournament->participants()->count();

        // Tính số tiền mỗi người phải đóng
        $feePerPerson = 0;
        if ($miniTournament->has_fee) {
            if ($miniTournament->auto_split_fee) {
                // Nếu đã lock final_fee_per_person, dùng giá trị đó
                if ($miniTournament->final_fee_per_person !== null) {
                    $feePerPerson = $miniTournament->final_fee_per_person;
                } else {
                    // Chia tự động: tổng tiền / số người hiện tại
                    $participantCount = $miniTournament->participants()->count();
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

        // Nếu kèo chia tiền tự động thì chỉ được thanh toán sau khi đã có ít nhất 1 trận hoàn tất
        if ($miniTournament->auto_split_fee) {
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $miniTournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();

            if (!$hasCompletedMatch) {
                return ResponseHelper::error(
                    'Kèo đang cài đặt chia tiền tự động, chỉ được thanh toán sau khi trận đấu đã kết thúc',
                    400
                );
            }
        }

        // Kiểm tra kèo có thu phí không
        if (!$miniTournament->has_fee) {
            return ResponseHelper::error('Kèo này không thu phí tham gia', 400);
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
        $extraAmount = 0;
        if (!empty($guestIds)) {
            // Validate: chỉ được đóng tiền cho guest mà user này bảo lãnh
            $validGuests = MiniParticipant::whereIn('id', $guestIds)
                ->where('guarantor_user_id', $userId)
                ->where('is_guest', true)
                ->where('payment_status', PaymentStatusEnum::PENDING)
                ->pluck('id')
                ->toArray();

            $validGuestCount = count($validGuests);
            $extraAmount = $feePerPerson * $validGuestCount;
            $guestIds = $validGuests; // chỉ giữ lại những guest hợp lệ
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
            if ($existingPayment) {
                if (in_array($existingPayment->status, [MiniParticipantPayment::STATUS_CONFIRMED])) {
                    // Payment đã confirmed rồi → chỉ cho phép thêm guest bằng cách tạo payment record riêng
                    if (empty($guestIds)) {
                        DB::rollBack();
                        return ResponseHelper::error('Thanh toán đã được xác nhận, không thể cập nhật', 400);
                    }

                    // Tạo payment record mới cho phần guest
                    $guestPayment = MiniParticipantPayment::create([
                        'mini_tournament_id' => $miniTournamentId,
                        'participant_id' => $existingPayment->participant_id,
                        'user_id' => $userId,
                        'amount' => $extraAmount,
                        'status' => MiniParticipantPayment::STATUS_PAID,
                        'receipt_image' => $receiptImage,
                        'note' => $data['note'] ?? null,
                        'paid_at' => now(),
                        'guest_ids' => $guestIds,
                    ]);

                    DB::commit();

                    return ResponseHelper::success(
                        new MiniParticipantPaymentResource($guestPayment->load(['user', 'confirmer'])),
                        'Thanh toán cho guest thành công, chờ chủ kèo xác nhận',
                        200
                    );
                }

                $existingPayment->update([
                    'amount' => $feePerPerson + $extraAmount,
                    'status' => MiniParticipantPayment::STATUS_PAID,
                    'receipt_image' => $receiptImage,
                    'note' => $data['note'] ?? null,
                    'paid_at' => now(),
                    'admin_note' => null,
                    'confirmed_at' => null,
                    'confirmed_by' => null,
                    'guest_ids' => !empty($guestIds) ? $guestIds : null,
                ]);

                $payment = $existingPayment;
            } else {
                $payment = MiniParticipantPayment::create([
                    'mini_tournament_id' => $miniTournamentId,
                    'participant_id' => $participant->id,
                    'user_id' => $userId,
                    'amount' => $feePerPerson + $extraAmount,
                    'status' => MiniParticipantPayment::STATUS_PAID,
                    'receipt_image' => $receiptImage,
                    'note' => $data['note'] ?? null,
                    'paid_at' => now(),
                    'guest_ids' => !empty($guestIds) ? $guestIds : null,
                ]);
            }

            DB::commit();

            return ResponseHelper::success(
                new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])),
                'Thanh toán thành công, chờ chủ kèo xác nhận',
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
    public function confirm(Request $request, $miniTournamentId, $paymentId)
    {
        return $this->processConfirmation($request, $miniTournamentId, $paymentId, true);
    }

    public function reject(Request $request, $miniTournamentId, $paymentId)
    {
        return $this->processConfirmation($request, $miniTournamentId, $paymentId, false);
    }

    public function markPaid(Request $request, $miniTournamentId, $paymentId)
    {
        $payment = MiniParticipantPayment::where('id', $paymentId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();
        if (!$payment) {
            // Debug: kiểm tra xem payment có tồn tại không (bất kể tournament)
            $paymentExists = MiniParticipantPayment::where('id', $paymentId)->first();
            if ($paymentExists) {
                return ResponseHelper::error(
                    "Thanh toán này không thuộc kèo đấu này. Payment tournament_id: {$paymentExists->mini_tournament_id}, expected: {$miniTournamentId}",
                    404
                );
            }
            return ResponseHelper::error('Không tìm thấy thanh toán', 404);
        }
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu thanh toán thành công', 403);
        }

        // Chỉ cho phép đánh dấu từ trạng thái PENDING hoặc REJECTED
        if (!in_array($payment->status, [MiniParticipantPayment::STATUS_PENDING, MiniParticipantPayment::STATUS_REJECTED])) {
            return ResponseHelper::error('Thanh toán đang ở trạng thái không thể đánh dấu', 400);
        }

        DB::beginTransaction();
        try {
            $payment->update([
                'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                'paid_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            // Update participant payment_status to confirmed
            $participant = MiniParticipant::find($payment->participant_id);
            if ($participant) {
                $participant->confirmPayment();
            }

            // Update payment_status for all guests in this payment
            if (!empty($payment->guest_ids)) {
                MiniParticipant::whereIn('id', $payment->guest_ids)
                    ->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
            }

            // Gửi notification cho thành viên
            $payment->load('user');
            if ($payment->user) {
                $payment->user->notify(new PaymentConfirmedNotification($payment));
            }

            DB::commit();

            return ResponseHelper::success(new MiniParticipantPaymentResource($payment->load(['user', 'confirmer'])), 'Đã đánh dấu thanh toán và xác nhận thành công');
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }
    }

    private function processConfirmation(Request $request, $miniTournamentId, $paymentId, bool $isConfirm)
    {
        $data = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Kiểm tra quyền organizer
        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xác nhận thanh toán', 403);
        }

        $payment = MiniParticipantPayment::where('id', $paymentId)
            ->where('mini_tournament_id', $miniTournamentId)
            ->first();

        if (!$payment) {
            return ResponseHelper::error('Không tìm thấy thanh toán', 404);
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

            // Update participant payment_status only if confirming
            if ($isConfirm) {
                $participant = MiniParticipant::find($payment->participant_id);
                if ($participant) {
                    $participant->confirmPayment();
                }

                // Update payment_status for all guests in this payment
                if (!empty($payment->guest_ids)) {
                    MiniParticipant::whereIn('id', $payment->guest_ids)
                        ->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
                }
            }

            // Gửi notification cho thành viên
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

        // Nếu kèo chia tiền tự động thì chỉ được nhắc thanh toán sau khi đã có ít nhất 1 trận hoàn tất
        if ($miniTournament->auto_split_fee) {
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $miniTournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();

            if (!$hasCompletedMatch) {
                return ResponseHelper::error(
                    'Kèo đang cài đặt chia tiền tự động, chỉ được nhắc thanh toán sau khi trận đấu đã kết thúc',
                    400
                );
            }
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

        // Nếu kèo chia tiền tự động thì chỉ được nhắc thanh toán sau khi đã có ít nhất 1 trận hoàn tất
        if ($miniTournament->auto_split_fee) {
            $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $miniTournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->exists();

            if (!$hasCompletedMatch) {
                return ResponseHelper::error(
                    'Kèo đang cài đặt chia tiền tự động, chỉ được nhắc thanh toán sau khi trận đấu đã kết thúc',
                    400
                );
            }
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

        // Tính số tiền phải đóng
        $feePerPerson = 0;
        if ($miniTournament->has_fee) {
            $participantCount = $miniTournament->participants()->count();
            if ($miniTournament->auto_split_fee) {
                // Nếu đã lock final_fee_per_person, dùng giá trị đó
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

        // Trả đầy đủ thông tin theo mini_tournament_id để FE luôn có dữ liệu hiển thị QR/phí,
        // kể cả khi user chưa có bản ghi payment.
        $data = array_merge([
            'mini_tournament_id' => $miniTournament->id,
            'participant_id' => $participant?->id,
            'has_fee' => (bool) $miniTournament->has_fee,
            'auto_split_fee' => (bool) $miniTournament->auto_split_fee,
            'auto_payment_created' => (bool) $miniTournament->auto_payment_created,
            'fee_amount' => (int) ($miniTournament->fee_amount ?? 0),
            'fee_per_person' => (int) ($feePerPerson ?? 0),
            'fee_description' => $miniTournament->fee_description,
            'qr_code_url' => $qrUrl,
        ], $paymentData);

        return ResponseHelper::success($data, 'Lấy thông tin thanh toán thành công');
    }
}
