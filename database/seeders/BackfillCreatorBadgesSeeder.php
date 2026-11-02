<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BackfillCreatorBadgesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $badgeService = app(BadgeService::class);

        $this->command->info('Bắt đầu quét và cấp bù huy hiệu cho người tạo kèo & giải đấu...');

        // 1. Đảm bảo các huy hiệu đã tồn tại trong DB
        $this->call(AutoBadgeSeeder::class);

        // 2. Cấp bù huy hiệu Tạo Kèo (HOST)
        $hostTiers = [
            20 => 'HOST_20',
            50 => 'HOST_50',
            100 => 'HOST_100',
            500 => 'HOST_500',
        ];

        $this->command->info('--- Đang quét người tạo kèo (MiniTournaments) ---');

        $hostCounts = MiniTournament::where('status', '!=', MiniTournament::STATUS_CANCELLED)
            ->whereNotNull('created_by')
            ->select('created_by', DB::raw('count(*) as total'))
            ->groupBy('created_by')
            ->having('total', '>=', 20)
            ->get();

        $totalHostAwarded = 0;

        foreach ($hostCounts as $item) {
            $userId = (int) $item->created_by;
            $total = (int) $item->total;

            foreach ($hostTiers as $threshold => $code) {
                if ($total >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($userId, $code);
                        $this->command->line("  ✓ Cấp {$code} cho User #{$userId} (Đã tạo {$total} kèo)");
                        $totalHostAwarded++;
                    }
                }
            }
        }
        $this->command->info("Đã cấp {$totalHostAwarded} huy hiệu tạo kèo.");

        // 3. Cấp bù huy hiệu Tạo Giải (ORGANIZER)
        $organizerTiers = [
            1 => 'ORGANIZER_1',
            5 => 'ORGANIZER_5',
            20 => 'ORGANIZER_20',
            50 => 'ORGANIZER_50',
        ];

        $this->command->info('--- Đang quét người tạo giải đấu (Tournaments) ---');

        $organizerCounts = Tournament::where('status', '!=', Tournament::CANCELLED)
            ->whereNotNull('created_by')
            ->select('created_by', DB::raw('count(*) as total'))
            ->groupBy('created_by')
            ->having('total', '>=', 1)
            ->get();

        $totalOrganizerAwarded = 0;

        foreach ($organizerCounts as $item) {
            $userId = (int) $item->created_by;
            $total = (int) $item->total;

            foreach ($organizerTiers as $threshold => $code) {
                if ($total >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($userId, $code);
                        $this->command->line("  ✓ Cấp {$code} cho User #{$userId} (Đã tạo {$total} giải)");
                        $totalOrganizerAwarded++;
                    }
                }
            }
        }
        $this->command->info("Đã cấp {$totalOrganizerAwarded} huy hiệu tạo giải đấu.");

        $this->command->info('Hoàn tất cấp bù huy hiệu thành công!');
    }
}

