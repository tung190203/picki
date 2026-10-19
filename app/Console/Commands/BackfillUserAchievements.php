<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\MiniTournament;
use App\Services\Club\UserAchievementRecorder;
use Illuminate\Console\Command;

class BackfillUserAchievements extends Command
{
    protected $signature = 'achievements:backfill {--chunk=100 : Số giải xử lý mỗi batch}';

    protected $description = 'Quét các giải đã CLOSED chưa có trong user_achievements và ghi vào (idempotent).';

    public function __construct(
        private UserAchievementRecorder $recorder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');

        $tournaments = Tournament::where('status', Tournament::CLOSED)
            ->orderBy('id')
            ->get();

        $this->info("Tournaments CLOSED: {$tournaments->count()}");
        $tWritten = 0;
        foreach ($tournaments as $t) {
            $tWritten += $this->recorder->recordForTournament($t);
        }
        $this->info("Đã ghi {$tWritten} cup rows.");

        $minis = MiniTournament::where('status', MiniTournament::STATUS_CLOSED)
            ->orderBy('id')
            ->get();

        $this->info("Mini-tournaments CLOSED: {$minis->count()}");
        $mWritten = 0;
        foreach ($minis as $m) {
            $mWritten += $this->recorder->recordForMiniTournament($m);
        }
        $this->info("Đã ghi {$mWritten} star rows.");

        return 0;
    }
}
