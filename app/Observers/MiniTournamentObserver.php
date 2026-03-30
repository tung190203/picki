<?php

namespace App\Observers;

use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubFundContribution;
use App\Models\Club\ClubWallet;
use App\Models\MiniTournament;
use App\Services\Club\ClubFundContributionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MiniTournamentObserver
{
    public function __construct(
        protected ClubFundContributionService $fundContributionService,
    ) {
    }

    /**
     * Handle the MiniTournament "created" event.
     * Tạo chi phí quỹ ngay khi tạo kèo nếu:
     * - use_club_fund = true, has_fee = true
     * - Kèo được tạo với status = STATUS_OPEN và start_time <= now (bắt đầu ngay)
     */
    public function created(MiniTournament $tournament): void
    {
        if (!$tournament->use_club_fund || !$tournament->has_fee) {
            return;
        }

        $this->handleTournamentStarted($tournament);
    }

    /**
     * Handle the MiniTournament "updated" event.
     * Tạo chi phí quỹ khi kèo chuyển sang STATUS_OPEN (bắt đầu).
     * Chỉ tạo 1 lần duy nhất (kiểm tra qua ClubExpense đã tồn tại với mini_tournament_id).
     */
    public function updated(MiniTournament $tournament): void
    {
        if (!$tournament->use_club_fund || !$tournament->has_fee) {
            return;
        }

        if (!$tournament->wasChanged('status')) {
            return;
        }

        if ((int) $tournament->status !== MiniTournament::STATUS_OPEN) {
            return;
        }

        $this->handleTournamentStarted($tournament);
    }

    /**
     * Logic chung: xử lý khi kèo CLB bắt đầu (status = OPEN).
     * - auto_split_fee=true: tạo ClubFundContribution cho member CLB + tạo ClubExpense OUT
     * - auto_split_fee=false: tạo ClubExpense OUT (fixed fee)
     */
    protected function handleTournamentStarted(MiniTournament $tournament): void
    {
        $club = $tournament->club;
        if (!$club) {
            return;
        }

        // === auto_split_fee: tạo ClubFundContribution + ClubExpense ===
        if ($tournament->auto_split_fee) {
            $this->handleAutoSplitFee($tournament, $club);
        }

        // === Tạo ClubExpense OUT (cả fixed fee và auto_split_fee) ===
        $this->createTournamentExpenseIfNeeded($tournament, $club);
    }

    /**
     * auto_split_fee=true: tạo ClubFundContribution cho member CLB.
     * - Organizer / guest được organizer bảo lãnh → CONFIRMED + wallet IN
     * - Member thường / guest thường → PENDING
     */
    protected function handleAutoSplitFee(MiniTournament $tournament, $club): void
    {
        $collection = $tournament->fundCollection;
        if (!$collection) {
            return;
        }

        // Lấy final_fee_per_person (đã lock bởi FinalizeCommand)
        $feePerPerson = $tournament->final_fee_per_person ?? 0;
        if ($feePerPerson <= 0) {
            return;
        }

        $clubMemberUserIds = $club->activeMembers()->pluck('user_id')->toArray();
        $organizerIds = $tournament->staff()->pluck('user_id')->toArray();

        // Lấy guest được organizer bảo lãnh
        $guaranteedGuestIds = $tournament->participants()
            ->where('is_guest', true)
            ->whereIn('guarantor_user_id', $organizerIds)
            ->pluck('user_id')
            ->toArray();

        // Cập nhật amount_due cho assignedMembers
        foreach ($clubMemberUserIds as $memberId) {
            $collection->assignedMembers()->updateExistingPivot($memberId, [
                'amount_due' => $feePerPerson,
            ]);
        }

        DB::transaction(function () use ($tournament, $collection, $clubMemberUserIds, $organizerIds, $guaranteedGuestIds, $feePerPerson) {
            foreach ($tournament->participants as $participant) {
                if (!in_array($participant->user_id, $clubMemberUserIds)) {
                    continue;
                }

                $isOrganizer = in_array($participant->user_id, $organizerIds);
                $isGuaranteedGuest = in_array($participant->user_id, $guaranteedGuestIds);

                if ($isOrganizer || $isGuaranteedGuest) {
                    try {
                        $this->fundContributionService->markMemberPaid($collection, $participant->user_id, $participant->user_id);
                    } catch (\Exception $e) {
                        Log::error('MiniTournamentObserver: Failed to markMemberPaid', [
                            'tournament_id' => $tournament->id,
                            'user_id' => $participant->user_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    ClubFundContribution::create([
                        'club_fund_collection_id' => $collection->id,
                        'user_id' => $participant->user_id,
                        'amount' => $feePerPerson,
                        'receipt_url' => null,
                        'note' => 'Chia đều kèo CLB - vui lòng nộp biên lai',
                        'status' => ClubFundContributionStatus::Pending,
                    ]);
                }
            }
        });
    }

    /**
     * Tạo ClubExpense + ClubWalletTransaction OUT nếu chưa tạo.
     */
    protected function createTournamentExpenseIfNeeded(MiniTournament $tournament, $club): void
    {
        // Chỉ tạo 1 lần duy nhất - kiểm tra qua ClubExpense đã tồn tại
        if (ClubExpense::where('mini_tournament_id', $tournament->id)->exists()) {
            return;
        }

        // Tổng chi phí = fee_amount (cố định)
        $totalExpense = (float) ($tournament->fee_amount ?? 0);

        // Với auto_split_fee: tính lại dựa trên final_fee_per_person (đã lock)
        if ($tournament->auto_split_fee && $tournament->final_fee_per_person) {
            $participantCount = $tournament->participants()->count();
            $totalExpense = $tournament->final_fee_per_person * $participantCount;
        }

        if ($totalExpense <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($tournament, $club, $totalExpense) {
                $clubExpense = ClubExpense::create([
                    'club_id' => $club->id,
                    'mini_tournament_id' => $tournament->id,
                    'title' => "Chi phí kèo: {$tournament->name}",
                    'amount' => $totalExpense,
                    'spent_by' => $tournament->created_by,
                    'spent_at' => now(),
                    'note' => "Tự động trừ quỹ kèo CLB. Kèo ID: {$tournament->id}.",
                ]);

                $mainWallet = $club->mainWallet;
                if (!$mainWallet) {
                    $mainWallet = ClubWallet::create([
                        'club_id' => $club->id,
                        'currency' => 'VND',
                    ]);
                }

                $transaction = $mainWallet->transactions()->create([
                    'direction' => ClubWalletTransactionDirection::Out,
                    'amount' => $totalExpense,
                    'source_type' => ClubWalletTransactionSourceType::TournamentFee,
                    'source_id' => $clubExpense->id,
                    'payment_method' => \App\Enums\PaymentMethod::Other,
                    'status' => ClubWalletTransactionStatus::Confirmed,
                    'description' => "Chi phí kèo: {$tournament->name}",
                    'created_by' => $tournament->created_by,
                    'confirmed_by' => $tournament->created_by,
                    'confirmed_at' => now(),
                    'included_in_club_fund' => true,
                ]);

                $clubExpense->updateQuietly(['wallet_transaction_id' => $transaction->id]);
            });
        } catch (\Exception $e) {
            Log::error('MiniTournamentObserver: Failed to create tournament expense', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

