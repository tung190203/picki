<?php

namespace App\Services\Club;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatusEnum;
use App\Jobs\SendPushJob;
use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\Club\ClubMember;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\User;
use App\Notifications\ClubFundContributionApprovedNotification;
use App\Notifications\ClubFundContributionRejectedNotification;
use App\Notifications\ClubFundContributionSubmittedNotification;
use App\Services\ImageOptimizationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClubFundContributionService
{
    public function __construct(
        protected ImageOptimizationService $imageService,
        protected ClubWalletService $walletService
    ) {
    }

    public function getContributions(ClubFundCollection $collection, array $filters): LengthAwarePaginator
    {
        $query = $collection->contributions()->with(['user', 'walletTransaction']);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function submitContribution(ClubFundCollection $collection, int $userId, $image, ?string $note = null): ClubFundContribution
    {
        $amountDue = (float) ($collection->amount_per_member ?? 0);

        if ($collection->status !== ClubFundCollectionStatus::Active) {
            $message = match ($collection->status) {
                ClubFundCollectionStatus::Pending => 'Mã QR này chưa được gắn với đợt thu. Vui lòng chờ admin tạo đợt thu và chọn mã QR này.',
                ClubFundCollectionStatus::Completed => 'Đợt thu đã kết thúc.',
                ClubFundCollectionStatus::Cancelled => 'Đợt thu đã bị hủy.',
                default => 'Đợt thu không còn hoạt động.',
            };
            throw new \Exception($message);
        }

        $existingPending = $collection->contributions()
            ->where('user_id', $userId)
            ->where('status', ClubFundContributionStatus::Pending)
            ->first();

        if ($existingPending) {
            throw new \Exception('Đóng góp của bạn đang chờ xác nhận');
        }

        $assigned = $collection->assignedMembers()->where('user_id', $userId)->first();
        if ($assigned) {
            $amountDue = (float) ($assigned->pivot?->amount_due ?? $amountDue);
        }

        if ($amountDue <= 0) {
            throw new \Exception('Số tiền cần đóng không hợp lệ');
        }

        $receiptUrl = $this->imageService->optimizeThumbnail($image, 'fund_contribution_receipts', 90);

        $contribution = ClubFundContribution::create([
            'club_fund_collection_id' => $collection->id,
            'user_id' => $userId,
            'amount' => $amountDue,
            'receipt_url' => $receiptUrl,
            'note' => $note,
            'status' => ClubFundContributionStatus::Pending,
        ]);

        // Sync MiniParticipantPayment nếu collection có liên kết MiniTournament
        $this->syncMiniTournamentPayment($contribution, 'pending');

        $club = $collection->club;
        $submitter = User::find($userId);
        $financeManagerUserIds = ClubMember::where('club_id', $club->id)
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active)
            ->whereIn('role', [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary, ClubMemberRole::Treasurer])
            ->pluck('user_id')
            ->unique()
            ->filter(fn ($id) => $id !== $userId);

        if ($submitter && $club) {
            $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
            $message = ($submitter->full_name ?: $submitter->email) . " đã nộp thanh toán cho khoản thu {$collectionTitle} tại CLB {$club->name}";
            $amountStr = number_format($contribution->amount, 0, ',', '.') . ' VND';

            foreach ($financeManagerUserIds as $recipientId) {
                $user = User::find($recipientId);
                if ($user) {
                    $user->notify(new ClubFundContributionSubmittedNotification($club, $collection, $contribution, $submitter));
                    SendPushJob::dispatch($user->id, 'Yêu cầu thanh toán mới', $message . ' - ' . $amountStr, [
                        'type' => 'CLUB_FUND_CONTRIBUTION_SUBMITTED',
                        'club_id' => (string) $club->id,
                        'club_fund_collection_id' => (string) $collection->id,
                        'club_fund_contribution_id' => (string) $contribution->id,
                    ]);
                }
            }
        }

        return $contribution;
    }

    public function confirmContribution(ClubFundContribution $contribution, int $confirmerId): ClubFundContribution
    {
        if ($contribution->status !== ClubFundContributionStatus::Pending) {
            throw new \Exception('Chỉ có thể xác nhận đóng góp đang pending');
        }

        $contribution = DB::transaction(function () use ($contribution, $confirmerId) {
            $contribution->confirm();

            // Sync MiniParticipantPayment nếu collection có liên kết MiniTournament
            $this->syncMiniTournamentPayment($contribution, 'confirmed');

            $collection = $contribution->fundCollection;
            $includedInClubFund = $collection->included_in_club_fund ?? true;

            if ($includedInClubFund) {
                $club = $collection->club;
                $mainWallet = $club->mainWallet;
                if (!$mainWallet) {
                    $mainWallet = $this->walletService->createWallet($club, ['currency' => 'VND']);
                }

                if ($contribution->walletTransaction) {
                    $contribution->walletTransaction->confirm($confirmerId);
                } else {
                    $description = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
                    $transaction = $mainWallet->transactions()->create([
                        'direction' => ClubWalletTransactionDirection::In,
                        'amount' => $contribution->amount,
                        'source_type' => ClubWalletTransactionSourceType::FundCollection,
                        'source_id' => $contribution->id,
                        'payment_method' => PaymentMethod::Other,
                        'status' => ClubWalletTransactionStatus::Confirmed,
                        'description' => $description,
                        'created_by' => $contribution->user_id,
                        'confirmed_by' => $confirmerId,
                        'confirmed_at' => now(),
                        'included_in_club_fund' => true,
                    ]);
                    $contribution->update(['wallet_transaction_id' => $transaction->id]);
                }
            }

            return $contribution->fresh();
        });

        $user = $contribution->user;
        $collection = $contribution->fundCollection;
        $club = $collection->club;
        if ($user && $club) {
            $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
            $message = "Yêu cầu thanh toán của bạn cho khoản thu {$collectionTitle} đã được chấp nhận";
            $user->notify(new ClubFundContributionApprovedNotification($club, $collection, $contribution));
            SendPushJob::dispatch($user->id, 'Thanh toán đã được chấp nhận', $message, [
                'type' => 'CLUB_FUND_CONTRIBUTION_APPROVED',
                'club_id' => (string) $club->id,
                'club_fund_collection_id' => (string) $collection->id,
                'club_fund_contribution_id' => (string) $contribution->id,
            ]);
        }

        return $contribution;
    }

    /**
     * Admin đánh dấu thành viên đã đóng mà không cần nộp biên lai.
     * Chỉ áp dụng cho fund-collection có club_activity_id (đợt thu từ sự kiện).
     */
    public function markMemberPaid(ClubFundCollection $collection, int $memberUserId, int $confirmerId): ClubFundContribution
    {
        // Cho phép mark-paid cho tất cả fund collection (không chỉ từ activity)
        
        $assigned = $collection->assignedMembers()->where('user_id', $memberUserId)->first();
        if (!$assigned) {
            throw new \Exception('Thành viên không có trong danh sách thu');
        }

        $amountDue = (float) ($assigned->pivot?->amount_due ?? $collection->amount_per_member ?? 0);
        if ($amountDue <= 0) {
            throw new \Exception('Số tiền cần đóng không hợp lệ');
        }

        $existing = $collection->contributions()->where('user_id', $memberUserId)->first();
        if ($existing) {
            if ($existing->status === ClubFundContributionStatus::Confirmed) {
                return $existing;
            }
            if ($existing->status === ClubFundContributionStatus::Pending) {
                return $this->confirmContribution($existing, $confirmerId);
            }
            throw new \Exception('Đóng góp đã bị từ chối, không thể đánh dấu đã đóng');
        }

        return DB::transaction(function () use ($collection, $memberUserId, $amountDue, $confirmerId) {
            $contribution = ClubFundContribution::create([
                'club_fund_collection_id' => $collection->id,
                'user_id' => $memberUserId,
                'amount' => $amountDue,
                'receipt_url' => null,
                'note' => 'Admin đánh dấu đã đóng (không cần biên lai)',
                'status' => ClubFundContributionStatus::Confirmed,
            ]);

            // Sync MiniParticipantPayment nếu collection có liên kết MiniTournament
            $this->syncMiniTournamentPayment($contribution, 'confirmed');

            $club = $collection->club;
            $includedInClubFund = $collection->included_in_club_fund ?? true;

            if ($includedInClubFund) {
                $mainWallet = $club->mainWallet;
                if (!$mainWallet) {
                    $mainWallet = $this->walletService->createWallet($club, ['currency' => 'VND']);
                }

                $description = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
                $transaction = $mainWallet->transactions()->create([
                    'direction' => ClubWalletTransactionDirection::In,
                    'amount' => $amountDue,
                    'source_type' => ClubWalletTransactionSourceType::FundCollection,
                    'source_id' => $contribution->id,
                    'payment_method' => PaymentMethod::Other,
                    'status' => ClubWalletTransactionStatus::Confirmed,
                    'description' => $description,
                    'created_by' => $memberUserId,
                    'confirmed_by' => $confirmerId,
                    'confirmed_at' => now(),
                    'included_in_club_fund' => true,
                ]);
                $contribution->update(['wallet_transaction_id' => $transaction->id]);
            }

            $collection->updateCollectedAmount();

            $user = User::find($memberUserId);
            if ($user && $club) {
                $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
                $message = "Thanh toán {$collectionTitle} đã được admin xác nhận";
                $user->notify(new ClubFundContributionApprovedNotification($club, $collection, $contribution));
                SendPushJob::dispatch($user->id, 'Thanh toán đã được xác nhận', $message, [
                    'type' => 'CLUB_FUND_CONTRIBUTION_APPROVED',
                    'club_id' => (string) $club->id,
                    'club_fund_collection_id' => (string) $collection->id,
                    'club_fund_contribution_id' => (string) $contribution->id,
                ]);
            }

            return $contribution->load(['user', 'walletTransaction']);
        });
    }

    public function rejectContribution(ClubFundContribution $contribution, ?string $rejectionReason = null): ClubFundContribution
    {
        $contribution->reject();

        // Sync MiniParticipantPayment nếu collection có liên kết MiniTournament
        $this->syncMiniTournamentPayment($contribution, 'rejected');

        $user = $contribution->user;
        $collection = $contribution->fundCollection;
        $club = $collection->club;
        if ($user && $club) {
            $collectionTitle = $collection->title ?: $collection->description ?: 'Đợt thu quỹ';
            $message = "Yêu cầu thanh toán của bạn cho khoản thu {$collectionTitle} đã bị từ chối";
            if ($rejectionReason) {
                $message .= ": {$rejectionReason}";
            }
            $user->notify(new ClubFundContributionRejectedNotification($club, $collection, $contribution, $rejectionReason));
            SendPushJob::dispatch($user->id, 'Thanh toán đã bị từ chối', $message, [
                'type' => 'CLUB_FUND_CONTRIBUTION_REJECTED',
                'club_id' => (string) $club->id,
                'club_fund_collection_id' => (string) $collection->id,
                'club_fund_contribution_id' => (string) $contribution->id,
            ]);
        }

        return $contribution;
    }

    /**
     * Sync MiniParticipantPayment khi user nộp/xác nhận/từ chối contribution qua ClubFundContributionController.
     * Chỉ sync nếu ClubFundCollection có liên kết với MiniTournament.
     */
    private function syncMiniTournamentPayment(ClubFundContribution $contribution, string $paymentStatus): void
    {
        // Tìm MiniTournament liên kết qua club_fund_collection_id
        $tournament = MiniTournament::where('club_fund_collection_id', $contribution->club_fund_collection_id)->first();
        if (!$tournament) {
            return;
        }

        $userId = $contribution->user_id;
        if (!$userId) {
            return;
        }

        $participant = $tournament->participants()->where('user_id', $userId)->first();
        if (!$participant) {
            return;
        }

        if ($paymentStatus === 'confirmed') {
            $participant->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
            MiniParticipantPayment::where('participant_id', $participant->id)
                ->where('status', '!=', MiniParticipantPayment::STATUS_CONFIRMED)
                ->update([
                    'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                    'confirmed_by' => $contribution->confirmed_by ?? auth()->id(),
                ]);
        } elseif ($paymentStatus === 'pending') {
            $participant->update(['payment_status' => PaymentStatusEnum::PENDING]);
        } elseif ($paymentStatus === 'rejected') {
            $participant->update(['payment_status' => PaymentStatusEnum::PENDING]);
            MiniParticipantPayment::where('participant_id', $participant->id)
                ->where('status', MiniParticipantPayment::STATUS_PAID)
                ->update(['status' => MiniParticipantPayment::STATUS_REJECTED]);
        }
    }
}
