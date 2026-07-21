<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadgeService;
use App\Enums\BadgeType;
use Illuminate\Console\Command;

class BackfillVerifiedUsers extends Command
{
    protected $signature = 'users:backfill-verified
        {--dry-run : Chỉ hiển thị thay đổi mà không lưu vào database}';

    protected $description = 'Cấp VERIFIED badge cho tất cả user chưa có badge có total_matches_has_anchor >= 10';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $badgeService = app(BadgeService::class);

        if ($dryRun) {
            $this->warn('[DRY-RUN] Chế độ chỉ xem — không lưu thay đổi vào database');
            $this->newLine();
        }

        // Find users without any badge who have >= 10 matches with anchors
        $query = User::where('is_guest', false)
            ->whereNotNull('total_matches_has_anchor')
            ->where('total_matches_has_anchor', '>=', 10);

        $candidates = $query->get(['id', 'full_name', 'total_matches_has_anchor']);

        // Filter to only those who don't have any badge yet
        $eligibleUsers = $candidates->filter(function ($user) use ($badgeService) {
            return !$badgeService->has_any_badge($user->id);
        });

        $this->info("Tìm thấy {$eligibleUsers->count()} user đủ điều kiện (chưa có badge, total_matches_has_anchor >= 10):");
        $this->table(
            ['ID', 'Tên', 'total_matches_has_anchor'],
            $eligibleUsers->map(fn($u) => [$u->id, $u->full_name ?? '-', $u->total_matches_has_anchor])
        );

        if ($dryRun) {
            $this->line('Không có thay đổi nào được lưu (--dry-run).');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($eligibleUsers as $user) {
            $badgeService->awardBadge($user->id, BadgeType::VERIFIED);
            $count++;
        }

        $this->info("Đã cấp VERIFIED badge cho {$count} user.");

        return Command::SUCCESS;
    }
}
