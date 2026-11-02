<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\UserAchievement;
use App\Services\Club\UserAchievementRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ lại bảng `user_achievements` theo đúng rule:
 * CHỈ ghi thành tích cho event đã đá xong TẤT CẢ trận.
 *
 * - `status = CLOSED` chỉ là "đóng đăng ký" (auto-close theo end_date có thể
 *   đóng giải còn trận chưa đá) → cup ghi lúc đó là sai, phải gỡ.
 * - Giải đã đá xong nhưng thiếu cup (vd. trận cuối xong sau khi giải đã đóng)
 *   → phải backfill.
 *
 * Idempotent: chạy nhiều lần cho kết quả giống nhau.
 */
class BackfillUserAchievements extends Command
{
    protected $signature = 'achievements:backfill';

    protected $description = 'Gỡ thành tích của event chưa đá xong và backfill cho event đã đá xong (idempotent).';

    public function __construct(
        private UserAchievementRecorder $recorder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->purgeOrphanRows();

        $tournaments = Tournament::where('status', Tournament::CLOSED)
            ->orderBy('id')
            ->get();

        $this->info("Tournaments CLOSED: {$tournaments->count()}");

        $purged = 0;
        $written = 0;
        foreach ($tournaments as $t) {
            if (! $t->hasAllMatchesCompleted()) {
                $deleted = $this->recorder->purgeForTournament($t);
                $purged += $deleted;

                if ($deleted > 0) {
                    $this->line("  ✗ T#{$t->id} '{$t->name}' — gỡ {$deleted} cup row (còn trận chưa đá)");
                }

                continue;
            }

            $written += $this->recorder->recordForTournament($t);
        }
        $this->info("Đã gỡ {$purged} cup row sai, ghi {$written} cup row.");

        $minis = MiniTournament::where('status', MiniTournament::STATUS_CLOSED)
            ->orderBy('id')
            ->get();

        $this->info("Mini-tournaments CLOSED: {$minis->count()}");

        $mPurged = 0;
        $mWritten = 0;
        foreach ($minis as $m) {
            if (! $m->hasAllMatchesCompleted()) {
                $deleted = $this->recorder->purgeForMiniTournament($m);
                $mPurged += $deleted;

                if ($deleted > 0) {
                    $this->line("  ✗ M#{$m->id} '{$m->name}' — gỡ {$deleted} star row (còn trận chưa đá)");
                }

                continue;
            }

            $mWritten += $this->recorder->recordForMiniTournament($m);
        }
        $this->info("Đã gỡ {$mPurged} star row sai, ghi {$mWritten} star row.");

        return 0;
    }

    /**
     * Gỡ thành tích trỏ tới event đã bị xoá (soft-delete) hoặc không còn tồn tại.
     *
     * Backfill chỉ quét event CLOSED còn "sống" nên không đụng tới các row
     * này → chúng vẫn còn trong `user_achievements` và vẫn xuất hiện trên
     * BXH CLB (vì read path đọc thẳng bảng, không join event).
     */
    private function purgeOrphanRows(): void
    {
        $miniIds = DB::table('mini_tournaments')->whereNull('deleted_at')->pluck('id');
        $tourIds = DB::table('tournaments')->whereNull('deleted_at')->pluck('id');

        $miniOrphans = UserAchievement::where('event_type', UserAchievement::TYPE_MINI_TOURNAMENT)
            ->whereNotIn('event_id', $miniIds)
            ->delete();

        $tourOrphans = UserAchievement::where('event_type', UserAchievement::TYPE_TOURNAMENT)
            ->whereNotIn('event_id', $tourIds)
            ->delete();

        if ($miniOrphans || $tourOrphans) {
            $this->info("Gỡ orphan: {$tourOrphans} cup row + {$miniOrphans} star row (event đã xoá).");
        }
    }
}
