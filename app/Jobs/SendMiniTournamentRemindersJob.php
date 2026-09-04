<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MiniTournament;
use App\Models\MiniTournamentUserNotification;
use App\Notifications\MiniTournamentReminder;
use Carbon\Carbon;

class SendMiniTournamentRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Chunk size khi duyệt các mini-tournament cần gửi reminder.
     * Giữ thấp để giải phóng memory giữa các batch, tránh chiếm PHP process quá lâu
     * trên shared hosting.
     */
    private const CHUNK_SIZE = 50;

    public function handle()
    {
        $now = Carbon::now();
        $reminderTime = $now->copy()->addMinutes(15);

        // Duyệt theo chunk thay vì ->get() toàn bộ - tránh memory spike
        // khi có nhiều mini-tournament cùng nằm trong cửa sổ 15 phút.
        MiniTournament::whereBetween('start_time', [$now, $reminderTime])
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($tournaments) {
                foreach ($tournaments as $tournament) {
                    // Chỉ load các subscription chưa được reminded cho tournament này.
                    $subscriptions = MiniTournamentUserNotification::where('mini_tournament_id', $tournament->id)
                        ->whereNull('reminded_at')
                        ->get();

                    foreach ($subscriptions as $sub) {
                        $sub->user->notify(new MiniTournamentReminder($tournament));
                        $sub->update(['reminded_at' => now()]);
                    }

                    // Giải phóng collection subscription sau mỗi tournament.
                    unset($subscriptions);
                }
            });
    }
}
