<?php

namespace App\Services;

use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\Club\ClubWalletTransaction;
use App\Models\Club\ClubWalletTransactionSourceType;
use App\Models\Club\ClubWalletTransactionDirection;
use App\Models\Club\ClubWalletTransactionStatus;
use App\Models\Club\Club;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentFundCollection;
use App\Models\TournamentFundContribution;
use App\Models\TournamentParticipantPayment;
use App\Models\User;
use App\Notifications\TournamentPaymentReminderNotification;
use App\Notifications\TournamentPaymentRejectedNotification;
use App\Notifications\TournamentPaymentConfirmedNotification;
use App\Events\SuperAdmin\PaymentConfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TournamentFundService
{
    /**
     * Tạo quỹ riêng của giải đấu (TournamentFundCollection)
     * Áp dụng khi has_financial_management=true và included_in_club_fund=false
     */
    public function createTournamentFundCollection(Tournament $tournament, array $validated): TournamentFundCollection
    {
        $feePerTeam = $this->calculateFeePerTeam($tournament, $validated);

        $collection = TournamentFundCollection::create([
            'tournament_id' => $tournament->id,
            'club_id' => $validated['club_id'] ?? null,
            'title' => $tournament->name,
            'description' => $validated['fee_description'] ?? null,
            'target_amount' => $validated['fee_amount'],
            'collected_amount' => 0,
            'currency' => 'VND',
            'start_date' => $tournament->start_date ?? now()->toDateString(),
            'end_date' => $tournament->end_date ?? null,
            'status' => 'active',
            'qr_code_url' => $validated['qr_code_url'] ?? null,
            'created_by' => $tournament->created_by,
        ]);

        $tournament->update(['tournament_fund_collection_id' => $collection->id]);

        // Tạo member entries + contributions cho participants hiện tại
        $this->assignMembersToCollection($collection, $tournament, $feePerTeam);

        return $collection;
    }

    /**
     * Tạo ClubFundCollection (ref mini_tournament) cho giải thuộc CLB
     * Áp dụng khi included_in_club_fund=true
     */
    public function createClubFundCollection(Tournament $tournament, array $validated, Club $club): ClubFundCollection
    {
        $collection = ClubFundCollection::create([
            'club_id' => $club->id,
            'title' => $tournament->name,
            'description' => $validated['fee_description'] ?? null,
            'target_amount' => $validated['fee_amount'],
            'amount_per_member' => $validated['fee_amount'],
            'currency' => 'VND',
            'start_date' => $tournament->start_date ?? now()->toDateString(),
            'end_date' => $tournament->end_date ?? null,
            'status' => 'active',
            'qr_code_url' => $validated['qr_code_url'] ?? null,
            'created_by' => $tournament->created_by,
            'included_in_club_fund' => true,
        ]);

        $tournament->update(['club_fund_collection_id' => $collection->id]);

        // Tham chiếu luồng mini_tournament: gán participants vào collection
        $feePerTeam = $this->calculateFeePerTeam($tournament, $validated);
        $this->assignMembersToClubCollection($collection, $tournament, $club, $feePerTeam);

        return $collection;
    }

    /**
     * Gán members vào TournamentFundCollection và tạo contributions
     */
    protected function assignMembersToCollection(TournamentFundCollection $collection, Tournament $tournament, int $feePerTeam): void
    {
        $userId = $tournament->created_by;
        $participantUserIds = $tournament->participants()->pluck('user_id')->toArray();

        foreach ($participantUserIds as $uid) {
            $collection->members()->attach($uid, ['amount_due' => $feePerTeam]);

            TournamentFundContribution::create([
                'tournament_fund_collection_id' => $collection->id,
                'user_id' => $uid,
                'amount' => $feePerTeam,
                'status' => $uid === $userId ? 'confirmed' : 'pending',
                'created_by' => $userId,
            ]);

            TournamentParticipantPayment::create([
                'tournament_id' => $tournament->id,
                'participant_id' => null,
                'user_id' => $uid,
                'amount' => $feePerTeam,
                'status' => $uid === $userId
                    ? TournamentParticipantPayment::STATUS_CONFIRMED
                    : TournamentParticipantPayment::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Gán members vào ClubFundCollection và tạo ClubFundContribution
     * Tham chiếu luồng ClubMiniTournamentController
     */
    protected function assignMembersToClubCollection(ClubFundCollection $collection, Tournament $tournament, Club $club, int $feePerTeam): void
    {
        $userId = $tournament->created_by;

        $clubMemberUserIds = $club->activeMembers()->pluck('user_id')->toArray();
        $participantUserIds = $tournament->participants()->pluck('user_id')->toArray();
        $commonUserIds = array_intersect($clubMemberUserIds, $participantUserIds);
        $organizerIds = $tournament->staff()->pluck('user_id')->toArray();

        // Organizers: exempt
        $exemptUserIds = $organizerIds;

        $allUserIds = array_unique(array_merge($commonUserIds, $organizerIds));

        foreach ($allUserIds as $uid) {
            $amountDue = in_array($uid, $exemptUserIds) ? 0 : $feePerTeam;
            $collection->assignedMembers()->attach($uid, ['amount_due' => $amountDue]);

            ClubFundContribution::create([
                'club_fund_collection_id' => $collection->id,
                'user_id' => $uid,
                'amount' => $amountDue,
                'status' => in_array($uid, $exemptUserIds) ? 'confirmed' : 'pending',
                'created_by' => $userId,
            ]);

            // Tạo payment entry
            TournamentParticipantPayment::create([
                'tournament_id' => $tournament->id,
                'participant_id' => null,
                'user_id' => $uid,
                'amount' => $amountDue,
                'status' => in_array($uid, $exemptUserIds)
                    ? TournamentParticipantPayment::STATUS_CONFIRMED
                    : TournamentParticipantPayment::STATUS_PENDING,
            ]);
        }

        // Nếu organizer là exempt, tạo wallet transaction cho họ
        foreach ($organizerIds as $oid) {
            $collection->contributions()
                ->where('user_id', $oid)
                ->where('status', 'confirmed')
                ->update([
                    'note' => 'Admin tạo kèo CLB - bao phí',
                ]);
        }
    }

    /**
     * Tính phí mỗi người
     */
    protected function calculateFeePerTeam(Tournament $tournament, array $validated): int
    {
        if (!($validated['has_fee'] ?? $tournament->has_fee ?? false)) {
            return 0;
        }

        $feeAmount = (int) ($validated['fee_amount'] ?? $tournament->fee_amount ?? 0);

        if (($validated['auto_split_fee'] ?? $tournament->auto_split_fee ?? false) && $tournament->max_team > 0) {
            return (int) round($feeAmount / $tournament->max_team);
        }

        return $feeAmount;
    }

    /**
     * Thành viên nộp receipt thanh toán
     */
    public function submitPayment(Tournament $tournament, User $user, array $data): TournamentParticipantPayment
    {
        $payment = TournamentParticipantPayment::updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
            ],
            [
                'amount' => $data['amount'] ?? $tournament->fee_per_person,
                'status' => TournamentParticipantPayment::STATUS_PAID,
                'receipt_image' => $data['receipt_image'] ?? null,
                'note' => $data['note'] ?? null,
                'paid_at' => now(),
                'admin_note' => null,
                'confirmed_at' => null,
                'confirmed_by' => null,
            ]
        );

        // Sync Participant.payment_status = PAY_PENDING (đã nộp, chờ duyệt)
        $participant = Participant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();
        if ($participant) {
            $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::PAY_PENDING]);
        }

        // Nếu có tournament fund collection, tạo contribution
        if ($tournament->tournament_fund_collection_id) {
            TournamentFundContribution::updateOrCreate(
                [
                    'tournament_fund_collection_id' => $tournament->tournament_fund_collection_id,
                    'user_id' => $user->id,
                ],
                [
                    'amount' => $payment->amount,
                    'receipt_url' => $data['receipt_image'] ?? null,
                    'note' => $data['note'] ?? null,
                    'status' => 'pending',
                ]
            );
        }

        return $payment;
    }

    /**
     * Admin xác nhận thanh toán
     */
    public function confirmPayment(TournamentParticipantPayment $payment, User $admin): void
    {
        $payment->update([
            'status' => TournamentParticipantPayment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => $admin->id,
        ]);

        // Sync Participant.payment_status = CONFIRMED
        $participant = Participant::where('tournament_id', $payment->tournament_id)
            ->where('user_id', $payment->user_id)
            ->first();
        if ($participant) {
            $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::CONFIRMED]);
        }

        // Sync tournament fund contribution
        if ($payment->tournament->tournament_fund_collection_id) {
            TournamentFundContribution::where('tournament_fund_collection_id', $payment->tournament->tournament_fund_collection_id)
                ->where('user_id', $payment->user_id)
                ->update(['status' => 'confirmed']);
        }

        $payment->user->notify(new TournamentPaymentConfirmedNotification($payment));

        PaymentConfirmed::dispatch(
            $payment->tournament_id,
            $payment->id,
            $payment->amount,
            $payment->user_id
        );
    }

    /**
     * Admin từ chối thanh toán
     */
    public function rejectPayment(TournamentParticipantPayment $payment, string $reason): void
    {
        $payment->update([
            'status' => TournamentParticipantPayment::STATUS_REJECTED,
            'admin_note' => $reason,
        ]);

        // Sync Participant.payment_status = PENDING (hoặc có thể giữ nguyên tuỳ logic)
        $participant = Participant::where('tournament_id', $payment->tournament_id)
            ->where('user_id', $payment->user_id)
            ->first();
        if ($participant) {
            $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::PENDING]);
        }

        // Sync TournamentFundContribution
        if ($payment->tournament->tournament_fund_collection_id) {
            TournamentFundContribution::where('tournament_fund_collection_id', $payment->tournament->tournament_fund_collection_id)
                ->where('user_id', $payment->user_id)
                ->update(['status' => 'rejected']);
        }

        $payment->user->notify(new TournamentPaymentRejectedNotification($payment, $reason));
    }

    /**
     * Admin đánh dấu đã thanh toán (không cần receipt)
     */
    public function markPaidManually(Tournament $tournament, Participant $participant, User $admin): TournamentParticipantPayment
    {
        $payment = TournamentParticipantPayment::updateOrCreate(
            [
                'tournament_id' => $tournament->id,
                'participant_id' => $participant->id,
            ],
            [
                'user_id' => $participant->user_id,
                'amount' => $tournament->fee_per_person,
                'status' => TournamentParticipantPayment::STATUS_CONFIRMED,
                'paid_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => $admin->id,
                'admin_note' => 'BTC đánh dấu đã thanh toán',
            ]
        );

        $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::CONFIRMED]);

        PaymentConfirmed::dispatch(
            $tournament->id,
            $payment->id,
            $payment->amount,
            $participant->user_id
        );

        if ($tournament->tournament_fund_collection_id && $participant->user_id) {
            TournamentFundContribution::where('tournament_fund_collection_id', $tournament->tournament_fund_collection_id)
                ->where('user_id', $participant->user_id)
                ->update(['status' => 'confirmed']);
        }

        if ($participant->user_id) {
            $payment->user->notify(new TournamentPaymentConfirmedNotification($payment));
        }

        return $payment;
    }

    /**
     * Gửi nhắc nhở cho 1 thành viên
     */
    public function remindUser(Tournament $tournament, Participant $participant): void
    {
        if (!$participant->user_id) {
            return;
        }

        $user = User::find($participant->user_id);
        if (!$user) {
            return;
        }

        $user->notify(new TournamentPaymentReminderNotification($tournament, $tournament->fee_per_person));
    }

    /**
     * Gửi nhắc nhở cho tất cả thành viên chưa thanh toán
     */
    public function remindAllPending(Tournament $tournament): array
    {
        $pendingPayments = TournamentParticipantPayment::where('tournament_id', $tournament->id)
            ->whereIn('status', [TournamentParticipantPayment::STATUS_PENDING, TournamentParticipantPayment::STATUS_REJECTED])
            ->with('participant')
            ->get();

        foreach ($pendingPayments as $payment) {
            if ($payment->participant) {
                $this->remindUser($tournament, $payment->participant);
            }
        }

        return $pendingPayments->pluck('id')->toArray();
    }
}
