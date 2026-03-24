<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Services\MiniTournamentPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FinalizeMiniTournamentPaymentsCommand extends Command
{
    protected $signature = 'mini-tournament:finalize-payments';
    protected $description = 'Finalize payments for mini tournaments with auto_split_fee when start_time is reached';

    public function handle(MiniTournamentPaymentService $paymentService)
    {
        $now = Carbon::now();

        // Tìm tất cả kèo có auto_split_fee = true, start_time <= now, và chưa finalize
        $tournaments = MiniTournament::where('auto_split_fee', true)
            ->where('has_fee', true)
            ->where('start_time', '<=', $now)
            ->where('auto_payment_created', 0)
            ->get();

        $this->info("Found {$tournaments->count()} tournaments to finalize");

        foreach ($tournaments as $tournament) {
            try {
                $this->finalizeTournamentPayments($tournament, $paymentService);
                $this->info("✓ Finalized payments for tournament: {$tournament->id} - {$tournament->name}");
            } catch (\Exception $e) {
                $this->error("✗ Error finalizing tournament {$tournament->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }

    private function finalizeTournamentPayments(MiniTournament $tournament, MiniTournamentPaymentService $paymentService)
    {
        // Ưu tiên dùng service để đảm bảo logic thống nhất:
        // - Organizer → CONFIRMED
        // - Guest bảo lãnh bởi organizer → CONFIRMED
        // - Member/guest khác: auto_approve = true → CONFIRMED, auto_approve = false → PENDING
        $paymentService->createAutoPaymentsWhenTournamentEnds($tournament);
    }
}
