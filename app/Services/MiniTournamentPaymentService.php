<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Notifications\MiniTournamentPaymentCreatedNotification;
use Illuminate\Support\Facades\DB;

class MiniTournamentPaymentService
{
    /**
     * Tạo khoản thu tự động khi kèo kết thúc (auto_split_fee = true)
     * - Tính final_fee_per_person dựa trên số người cuối cùng
     * - Tạo payment record cho tất cả participants
     * - Organizer → CONFIRMED (miễn phí)
     * - Guest bảo lãnh bởi organizer → CONFIRMED
     * - Member khác và Guest bảo lãnh bởi member khác → PENDING
     */
    public function createAutoPaymentsWhenTournamentEnds(MiniTournament $tournament): bool
    {
        // Chỉ xử lý kèo có thu phí và chia tiền tự động
        if (!$tournament->has_fee || !$tournament->auto_split_fee) {
            return false;
        }

        // Nếu đã tạo rồi, không tạo lại
        if ($tournament->auto_payment_created) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Lấy tất cả participants (bao gồm cả chủ kèo nếu họ tham gia)
            $participants = $tournament->participants()->get();
            $participantCount = $participants->count();

            if ($participantCount === 0) {
                DB::commit();
                return false;
            }

            // Tính final_fee_per_person dựa trên số người cuối cùng
            $finalFeePerPerson = round($tournament->fee_amount / $participantCount);

            // Lock fee_per_person
            $tournament->update([
                'final_fee_per_person' => $finalFeePerPerson,
                'auto_payment_created' => true,
            ]);

            // Lấy organizers
            $organizers = $tournament->staff()->pluck('users.id')->toArray();

            // Tạo hoặc cập nhật payment cho tất cả participants
            foreach ($participants as $participant) {
                $isOrganizer = in_array($participant->user_id, $organizers);

                // Kiểm tra guest bảo lãnh bởi organizer
                $isGuestByOrganizer = $participant->is_guest
                    && $participant->guarantor_user_id !== null
                    && in_array($participant->guarantor_user_id, $organizers);

                // Xác định status: organizer và guest bảo lãnh bởi organizer → CONFIRMED
                $shouldBeConfirmed = $isOrganizer || $isGuestByOrganizer;

                // Kiểm tra xem đã có payment chưa
                $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                    ->where('participant_id', $participant->id)
                    ->first();

                if ($existingPayment) {
                    // Cập nhật lại amount cho payments đã tạo trước đó
                    // KHÔNG thay đổi status đã confirmed
                    $existingPayment->update([
                        'amount' => $finalFeePerPerson,
                    ]);
                } else {
                    // Tạo payment record mới
                    $payment = MiniParticipantPayment::create([
                        'mini_tournament_id' => $tournament->id,
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'amount' => $finalFeePerPerson,
                        'status' => $shouldBeConfirmed
                            ? MiniParticipantPayment::STATUS_CONFIRMED
                            : MiniParticipantPayment::STATUS_PENDING,
                        'paid_at' => $shouldBeConfirmed ? now() : null,
                        'confirmed_at' => $shouldBeConfirmed ? now() : null,
                        'confirmed_by' => $shouldBeConfirmed ? $participant->user_id : null,
                    ]);

                    // Gửi thông báo cho người cần thanh toán (không gửi cho organizer)
                    if (!$shouldBeConfirmed) {
                        $participant->user?->notify(
                            new MiniTournamentPaymentCreatedNotification($tournament, $payment, $finalFeePerPerson)
                        );
                    }
                }

                // Cập nhật participant payment_status nếu cần
                $participant->update([
                    'payment_status' => $shouldBeConfirmed
                        ? PaymentStatusEnum::CONFIRMED
                        : PaymentStatusEnum::PENDING,
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Tính toán lại fee_per_person khi có người rút khỏi (trước khi kèo kết thúc)
     * Chỉ áp dụng nếu auto_split_fee = true và chưa lock final_fee_per_person
     */
    public function recalculateFeePerPerson(MiniTournament $tournament): void
    {
        if (!$tournament->has_fee || !$tournament->auto_split_fee) {
            return;
        }

        // Nếu đã lock final_fee_per_person, không tính lại
        if ($tournament->final_fee_per_person !== null) {
            return;
        }

        // Tính lại dựa trên số người hiện tại
        $participantCount = $tournament->participants()->count();
        if ($participantCount === 0) {
            return;
        }

        // Có thể log hoặc broadcast event để notify clients về thay đổi fee
    }
}
