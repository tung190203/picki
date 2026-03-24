<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Services\MiniTournamentPaymentService;
use Illuminate\Console\Command;

class CreateAutoPaymentsCommand extends Command
{
    protected $signature = 'mini-tournaments:create-auto-payments';
    protected $description = 'Tạo khoản thu tự động khi kèo bắt đầu (auto_split_fee = true)';

    public function handle(MiniTournamentPaymentService $paymentService): int
    {
        $this->info('Bắt đầu tạo khoản thu tự động...');

        try {
            // Lấy tất cả kèo đã bắt đầu, có thu phí, chia tiền tự động, nhưng chưa tạo payment
            $tournaments = MiniTournament::where('has_fee', 1)
                ->where('auto_split_fee', 1)
                ->where('auto_payment_created', 0)
                ->where('start_time', '<=', now())
                ->get();

            $count = 0;
            foreach ($tournaments as $tournament) {
                try {
                    if ($paymentService->createAutoPaymentsWhenTournamentEnds($tournament)) {
                        $this->info("✓ Tạo khoản thu cho kèo: {$tournament->name} (ID: {$tournament->id})");
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->error("✗ Lỗi khi tạo khoản thu cho kèo {$tournament->name}: {$e->getMessage()}");
                }
            }

            $this->info("Hoàn thành! Tạo khoản thu cho {$count} kèo");
            return 0;
        } catch (\Exception $e) {
            $this->error("Lỗi: {$e->getMessage()}");
            return 1;
        }
    }
}
