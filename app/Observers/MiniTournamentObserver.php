<?php

namespace App\Observers;

use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubWallet;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Services\MiniTournamentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MiniTournamentObserver
{
    /**
     * Handle the MiniTournament "created" event.
     * Xử lý use_club_fund = true được thực hiện trong ClubMiniTournamentController.
     * Observer này chỉ xử lý included_in_club_fund (đợt thu quỹ chung CLB).
     */
    public function created(MiniTournament $tournament): void
    {
        // use_club_fund = true → đã xử lý trong ClubMiniTournamentController::createClubExpenseForTournament()
        // Không làm gì ở đây để tránh duplicate expense khi observer chạy sau controller transaction
    }

    /**
     * Handle the MiniTournament "updated" event.
     */
    public function updated(MiniTournament $tournament): void
    {
        // Hook use_club_fund: tạo expense khi chuyển sang STATUS_OPEN
        $this->handleClubFundExpense($tournament);

        // Hook auto-create: tạo occurrence tiếp theo khi kèo lặp được đóng
        $this->handleRecurringAutoCreate($tournament);
    }

    /**
     * Khi kèo chuyển sang STATUS_OPEN (bắt đầu) mà use_club_fund = true,
     * tạo expense nếu chưa có (trường hợp kèo được tạo ở endpoint khác ClubMiniTournamentController).
     */
    protected function handleClubFundExpense(MiniTournament $tournament): void
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

        $this->createTournamentExpenseIfNeeded($tournament);
    }

    /**
     * Khi kèo lặp được đóng (STATUS_CLOSED), tạo occurrence tiếp theo ngay lập tức.
     * Đảm bảo người dùng không phải đợi job hàng ngày mới thấy kèo tiếp theo.
     */
    protected function handleRecurringAutoCreate(MiniTournament $tournament): void
    {
        if (!$tournament->wasChanged('status')) {
            return;
        }

        if ((int) $tournament->status !== MiniTournament::STATUS_CLOSED) {
            return;
        }

        if (!$tournament->isRecurring() || $tournament->isRecurrenceSeriesCancelled()) {
            return;
        }

        try {
            $organizerId = $this->resolveOrganizerId($tournament);
            if (!$organizerId) {
                return;
            }

            $service = app(MiniTournamentService::class);
            $nextOccurrence = $tournament->calculateNextOccurrence(Carbon::now());
            if ($nextOccurrence) {
                $service->createNextOccurrenceIfMissing(
                    $tournament,
                    $nextOccurrence,
                    $organizerId,
                    $tournament->recurrence_series_id
                );
            }
        } catch (\Exception $e) {
            Log::error('MiniTournamentObserver: Failed to create next occurrence', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve organizer ID từ staff roles hoặc first participant.
     */
    protected function resolveOrganizerId(MiniTournament $tournament): ?int
    {
        $tournament->loadMissing(['miniTournamentStaffs', 'participants']);

        $organizerStaff = $tournament->miniTournamentStaffs
            ->firstWhere('role', MiniTournamentStaff::ROLE_ORGANIZER);

        if ($organizerStaff?->user_id) {
            return (int) $organizerStaff->user_id;
        }

        $firstParticipant = $tournament->participants->first();
        if ($firstParticipant?->user_id) {
            return (int) $firstParticipant->user_id;
        }

        return null;
    }

    /**
     * Tạo ClubExpense + ClubWalletTransaction OUT nếu chưa tạo.
     * Dùng cho trường hợp kèo use_club_fund được tạo ngoài ClubMiniTournamentController.
     */
    protected function createTournamentExpenseIfNeeded(MiniTournament $tournament): void
    {
        if (ClubExpense::where('mini_tournament_id', $tournament->id)->exists()) {
            return;
        }

        $totalExpense = (float) ($tournament->fee_amount ?? 0);
        if ($totalExpense <= 0) {
            return;
        }

        $club = $tournament->club;
        if (!$club) {
            return;
        }

        try {
            DB::transaction(function () use ($tournament, $club, $totalExpense) {
                $clubExpense = ClubExpense::create([
                    'club_id' => $club->id,
                    'mini_tournament_id' => $tournament->id,
                    'title' => $tournament->name,
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
