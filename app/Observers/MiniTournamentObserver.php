<?php

namespace App\Observers;

use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubWallet;
use App\Models\MiniTournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MiniTournamentObserver
{
    /**
     * Handle the MiniTournament "created" event.
     * Khi use_club_fund = true: kèo là miễn phí, CLB chi tiền.
     * - Tạo ClubExpense + ClubWalletTransaction OUT (trừ quỹ CLB)
     * - Không tạo ClubFundContribution (không ai phải đóng)
     */
    public function created(MiniTournament $tournament): void
    {
        if (!$tournament->use_club_fund) {
            return;
        }

        $this->handleTournamentStarted($tournament);
    }

    /**
     * Handle the MiniTournament "updated" event.
     * Khi kèo chuyển sang STATUS_OPEN (bắt đầu), tạo chi phí quỹ.
     * Chỉ tạo 1 lần duy nhất (kiểm tra qua ClubExpense đã tồn tại với mini_tournament_id).
     */
    public function updated(MiniTournament $tournament): void
    {
        if (!$tournament->use_club_fund) {
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
     * Logic chung: xử lý khi kèo CLB bắt đầu (status = OPEN hoặc tạo ngay khi use_club_fund=true).
     * use_club_fund = true → kèo miễn phí cho tất cả, CLB chi tiền.
     * - Tạo ClubExpense + ClubWalletTransaction OUT → Lịch sử thu chi CLB
     * - Không tạo ClubFundContribution (vì không ai phải đóng)
     */
    protected function handleTournamentStarted(MiniTournament $tournament): void
    {
        $club = $tournament->club;
        if (!$club) {
            return;
        }

        $this->createTournamentExpenseIfNeeded($tournament, $club);
    }

    /**
     * Tạo ClubExpense + ClubWalletTransaction OUT nếu chưa tạo.
     * Số tiền chi = fee_amount (số tiền CLB chi cho kèo đấu).
     */
    protected function createTournamentExpenseIfNeeded(MiniTournament $tournament, $club): void
    {
        // Chỉ tạo 1 lần duy nhất - kiểm tra qua ClubExpense đã tồn tại
        if (ClubExpense::where('mini_tournament_id', $tournament->id)->exists()) {
            return;
        }

        $totalExpense = (float) ($tournament->fee_amount ?? 0);

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
                    'note' => "Quỹ chi kèo CLB. Kèo ID: {$tournament->id}.",
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
                    'description' => "Quỹ chi kèo: {$tournament->name}",
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
