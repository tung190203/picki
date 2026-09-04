<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Services\MiniTournamentPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FinalizeMiniTournamentPaymentsCommand extends Command
{
    /**
     * --limit giới hạn số tournament xử lý mỗi lần chạy cron.
     * Mặc định 50 để tránh giữ PHP process quá lâu trên shared hosting.
     */
    protected $signature = 'mini-tournament:finalize-payments
                            {--limit=50 : Max tournaments to process per run}';

    protected $description = 'Finalize payments for mini tournaments with auto_split_fee when start_time is reached';

    public function handle(MiniTournamentPaymentService $paymentService)
    {
        $now = Carbon::now();
        $limit = max(1, (int) $this->option('limit'));

        // Lấy danh sách ID trước (không load toàn bộ model) - giảm memory peak.
        $tournamentIds = MiniTournament::where('auto_split_fee', true)
            ->where('has_fee', true)
            ->where('start_time', '<=', $now)
            ->where('auto_payment_created', 0)
            ->limit($limit)
            ->pluck('id')
            ->toArray();

        $found = count($tournamentIds);
        $this->info("Found {$found} tournaments to finalize (limit={$limit})");

        foreach ($tournamentIds as $id) {
            try {
                // Load từng model một - tránh load toàn bộ vào memory.
                $tournament = MiniTournament::find($id);
                if (!$tournament) {
                    continue;
                }
                $this->finalizeTournamentPayments($tournament, $paymentService);
                $this->info("✓ Finalized payments for tournament: {$id} - {$tournament->name}");
                // Giải phóng model khỏi memory trước khi xử lý cái tiếp theo.
                unset($tournament);
            } catch (\Exception $e) {
                $this->error("✗ Error finalizing tournament {$id}: {$e->getMessage()}");
                Log::error('FinalizeMiniTournamentPayments failed', [
                    'tournament_id' => $id,
                    'error' => $e->getMessage(),
                ]);
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
