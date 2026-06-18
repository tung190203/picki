<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BackfillVerifiedUsers extends Command
{
    protected $signature = 'users:backfill-verified
        {--dry-run : Chỉ hiển thị thay đổi mà không lưu vào database}';

    protected $description = 'Gán is_verified=true cho tất cả user chưa verified có total_matches_has_anchor >= 10';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Chế độ chỉ xem — không lưu thay đổi vào database');
            $this->newLine();
        }

        $query = User::where('is_verified', false)
            ->where('is_guest', false)
            ->whereNotNull('total_matches_has_anchor')
            ->where('total_matches_has_anchor', '>=', 10);

        $candidates = $query->get(['id', 'full_name', 'total_matches_has_anchor']);

        $this->info("Tìm thấy {$candidates->count()} user đủ điều kiện:");
        $this->table(
            ['ID', 'Tên', 'total_matches_has_anchor'],
            $candidates->map(fn($u) => [$u->id, $u->full_name ?? '-', $u->total_matches_has_anchor])
        );

        if ($dryRun) {
            $this->line('Không có thay đổi nào được lưu (--dry-run).');
            return Command::SUCCESS;
        }

        $updated = $query->update(['is_verified' => true]);

        $this->info("Đã cấp tick xanh cho {$updated} user.");

        return Command::SUCCESS;
    }
}
