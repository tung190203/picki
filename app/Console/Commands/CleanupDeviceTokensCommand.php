<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

/**
 * Xóa các device token đã quá 60 ngày không online.
 * Tách thành command riêng để có thể chạy background (closure không chạy được).
 */
class CleanupDeviceTokensCommand extends Command
{
    protected $signature = 'device-tokens:cleanup-stale
                            {--days=60 : Xóa token không thấy trong N ngày}';

    protected $description = 'Xóa các device token đã quá hạn sử dụng';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = DeviceToken::where('last_seen_at', '<', now()->subDays($days))->delete();

        $this->info("Đã xóa {$deleted} device token cũ hơn {$days} ngày.");
        return self::SUCCESS;
    }
}
