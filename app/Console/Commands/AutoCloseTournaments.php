<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\TournamentService;
use Illuminate\Console\Command;

class AutoCloseTournaments extends Command
{
    protected $signature = 'tournaments:auto-close';

    protected $description = 'Tự động đóng giải đấu khi end_date < now() và cập nhật stats cho participants';

    public function __construct(
        private TournamentService $tournamentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tournaments = Tournament::where('status', '!=', Tournament::CLOSED)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('Khong co giai dau nao can dong.');
            return 0;
        }

        foreach ($tournaments as $tournament) {
            $this->tournamentService->closeTournament($tournament);
            $this->info("Da dong giai #{$tournament->id} '{$tournament->name}'.");
        }

        $this->info("Da dong {$tournaments->count()} giai dau.");
        return 0;
    }
}
