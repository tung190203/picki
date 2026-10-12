<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Services\Club\ClubGuestEventService;
use Illuminate\Console\Command;

/**
 * Cronjob chạy mỗi 2 phút.
 * Quét các giải vừa đến start_time trong khoảng [now - 1 giờ, now]
 * → trigger club_guests cho tất cả participants.
 *
 * Idempotent nhờ upsertFromEvent (user đã có → tăng play_count, user mới → tạo mới).
 */
class SyncClubGuestsFromStartedEvents extends Command
{
    protected $signature = 'clubs:sync-guests-from-started-events
                            {--minutes=120 : Số phút lookback (mặc định 120 = 2 giờ)}
                            {--dry-run : Chỉ log, không ghi}';

    protected $description = 'Cronjob: cập nhật club_guests cho các giải vừa đến start_time';

    public function handle(ClubGuestEventService $guestEventService): int
    {
        $minutes = (int) $this->option('minutes');
        $isDryRun = $this->option('dry-run');
        $fromTime = now()->subMinutes($minutes);

        $totalMini = 0;
        $totalTournament = 0;

        // === Mini Tournaments ===
        // start_time trong [now - X phút, now] = vừa bắt đầu
        $miniTournaments = MiniTournament::whereNotNull('club_id')
            ->whereNotNull('start_time')
            ->where('start_time', '<=', now())
            ->where('start_time', '>=', $fromTime)
            ->get();

        foreach ($miniTournaments as $mini) {
            if ($isDryRun) {
                $this->line("[DRY] MiniTournament #{$mini->id} start={$mini->start_time}");
                continue;
            }

            $count = $guestEventService->triggerMiniTournament($mini);
            if ($count > 0) {
                $this->info("MiniTournament #{$mini->id}: +{$count} new guests");
            }
            $totalMini += $count;
        }

        // === Tournaments ===
        $tournaments = Tournament::whereNotNull('club_id')
            ->whereNotNull('start_date')
            ->where('start_date', '<=', now())
            ->where('start_date', '>=', $fromTime)
            ->get();

        foreach ($tournaments as $t) {
            if ($isDryRun) {
                $this->line("[DRY] Tournament #{$t->id} start={$t->start_date}");
                continue;
            }

            $count = $guestEventService->triggerTournament($t);
            if ($count > 0) {
                $this->info("Tournament #{$t->id}: +{$count} new guests");
            }
            $totalTournament += $count;
        }

        $this->info("Xong. New guests: mini={$totalMini}, tournament={$totalTournament}");

        return self::SUCCESS;
    }
}
