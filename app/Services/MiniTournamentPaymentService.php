<?php

namespace App\Services;

use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatusEnum;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\MiniTournament;
use App\Jobs\SendPushJob;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Notifications\MiniTournamentPaymentCreatedNotification;
use App\Services\Club\ClubFundContributionService;
use App\Services\Club\ClubWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MiniTournamentPaymentService
{
    public function __construct(
        protected ClubFundContributionService $fundContributionService,
        protected ClubWalletService $walletService,
    ) {
    }
    /**
     * Tạo khoản thu tự động khi kèo bắt đầu (auto_split_fee = true)
     * - Tính final_fee_per_person dựa trên số người tại thời điểm start_time
     * - Organizer + guest được organizer bảo lãnh → CONFIRMED (confirmed_payments)
     * - Member thường + guest bảo lãnh bởi member khác → PENDING (pending_payments)
     * - auto_approve không ảnh hưởng đến việc xếp confirmed/pending
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

            // Load fundCollection để sync ClubFundContribution + wallet transaction (nếu có)
            $tournament->load('fundCollection');

            // Tạo hoặc cập nhật payment cho tất cả participants
            foreach ($participants as $participant) {
                $isOrganizer = in_array($participant->user_id, $organizers);

                // Kiểm tra guest bảo lãnh bởi organizer
                $isGuestByOrganizer = $participant->is_guest
                    && $participant->guarantor_user_id !== null
                    && in_array($participant->guarantor_user_id, $organizers);

                // Xác định status:
                // Chỉ organizer + guest được organizer bảo lãnh → CONFIRMED
                // Tất cả participant khác (member thường, guest bảo lãnh bởi member) → PENDING
                // auto_approve chỉ ảnh hưởng đến trạng thái payment record (đã đóng / chưa đóng)
                // chứ không thay đổi logic xếp confirmed_payments vs pending_payments
                $shouldBeConfirmed = $isOrganizer || $isGuestByOrganizer;

                // Kiểm tra xem đã có payment chưa
                $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                    ->where('participant_id', $participant->id)
                    ->first();

                if ($existingPayment) {
                    // Cập nhật amount cho payments đã tạo trước đó
                    $updateData = ['amount' => $finalFeePerPerson];

                    // Nếu shouldBeConfirmed: cập nhật thành CONFIRMED
                    // Nếu KHÔNG shouldBeConfirmed nhưng đang CONFIRMED: chuyển về PENDING
                    if ($shouldBeConfirmed && $existingPayment->status !== MiniParticipantPayment::STATUS_CONFIRMED) {
                        $updateData['status'] = MiniParticipantPayment::STATUS_CONFIRMED;
                        $updateData['paid_at'] = now();
                        $updateData['confirmed_at'] = now();
                        $updateData['confirmed_by'] = $participant->user_id;
                    } elseif (!$shouldBeConfirmed && $existingPayment->status === MiniParticipantPayment::STATUS_CONFIRMED) {
                        $updateData['status'] = MiniParticipantPayment::STATUS_PENDING;
                        $updateData['paid_at'] = null;
                        $updateData['confirmed_at'] = null;
                        $updateData['confirmed_by'] = null;
                    }

                    $existingPayment->update($updateData);
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

                    // Gửi notification cho người cần thanh toán (không gửi cho organizer/guest by organizer)
                    if (!$shouldBeConfirmed && $participant->user_id) {
                        $participant->user?->notify(
                            new MiniTournamentPaymentCreatedNotification($tournament, $payment, $finalFeePerPerson)
                        );
                        // Gửi FCM push notification
                        SendPushJob::dispatch(
                            $participant->user_id,
                            'Yêu cầu thanh toán kèo đấu',
                            "Kèo \"{$tournament->name}\" đã bắt đầu. Bạn cần thanh toán {$finalFeePerPerson} VND để hoàn tất.",
                            [
                                'type' => 'mini_tournament_payment_created',
                                'mini_tournament_id' => (string) $tournament->id,
                                'payment_id' => (string) $payment->id,
                            ]
                        );
                    }
                }

                // Cập nhật participant payment_status nếu cần
                $participant->update([
                    'payment_status' => $shouldBeConfirmed
                        ? PaymentStatusEnum::CONFIRMED
                        : PaymentStatusEnum::PENDING,
                ]);

                // Khi organizer/guest được auto-confirmed → tạo ClubFundContribution Confirmed + wallet tx
                if ($shouldBeConfirmed && $participant->user_id) {
                    $this->fundContributionService->createOrganizerConfirmedContribution(
                        $tournament->fundCollection,
                        $participant->user_id,
                        $participant->user_id,
                        (float) $finalFeePerPerson
                    );
                }
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Tạo ClubFundContribution Confirmed cho organizer exempt (auto_confirmed).
     * Tương tự markOrganizerExempt nhưng không cần user đã trong assignedMembers.
     * LUÔN tạo wallet transaction IN để hiển thị trong lịch sử thu chi CLB.
     */
    public function createOrganizerConfirmedContribution(
        ClubFundCollection $collection,
        int $userId,
        int $confirmerId,
        float $feeAmount
    ): ?ClubFundContribution {
        $existing = $collection->contributions()->where('user_id', $userId)->first();
        if ($existing) {
            if ($existing->status === ClubFundContributionStatus::Confirmed) {
                return $existing;
            }
            if ($existing->status === ClubFundContributionStatus::Pending) {
                return $this->fundContributionService->confirmContribution($existing, $confirmerId);
            }
        }

        $contribution = ClubFundContribution::create([
            'club_fund_collection_id' => $collection->id,
            'user_id' => $userId,
            'amount' => $feeAmount,
            'receipt_url' => null,
            'note' => 'Chủ kèo/guest được bao phí - tự động xác nhận',
            'status' => ClubFundContributionStatus::Confirmed,
        ]);

        $this->fundContributionService->syncMiniTournamentPayment($contribution, 'confirmed');

        if ($collection->included_in_club_fund ?? true) {
            $club = $collection->club;
            if ($club) {
                $mainWallet = $club->mainWallet;
                if (!$mainWallet) {
                    $mainWallet = $this->walletService->createWallet($club, ['currency' => 'VND']);
                }

                $description = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
                $transaction = $mainWallet->transactions()->create([
                    'direction' => ClubWalletTransactionDirection::In,
                    'amount' => $feeAmount,
                    'source_type' => ClubWalletTransactionSourceType::FundCollection,
                    'source_id' => $contribution->id,
                    'payment_method' => PaymentMethod::Other,
                    'status' => ClubWalletTransactionStatus::Confirmed,
                    'description' => $description,
                    'created_by' => $userId,
                    'confirmed_by' => $confirmerId,
                    'confirmed_at' => now(),
                    'included_in_club_fund' => true,
                ]);
                $contribution->update(['wallet_transaction_id' => $transaction->id]);
            }
        }

        $collection->updateCollectedAmount();

        return $contribution;
    }

    public function rejectContribution(ClubFundContribution $contribution, ?string $rejectionReason = null): ClubFundContribution
    {
        return $this->fundContributionService->rejectContribution($contribution, $rejectionReason);
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
