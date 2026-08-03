<?php

namespace App\Console\Commands;

use App\Http\Controllers\MatchesController;
use App\Models\TournamentType;
use Illuminate\Console\Command;

class RecalculateTournamentRankings extends Command
{
    protected $signature = 'tournament:recalculate-rankings {tournament_type_id : ID of tournament type to recalculate}';
    protected $description = 'Recalculate rankings for a specific tournament type';

    public function handle(): int
    {
        $tournamentTypeId = (int) $this->argument('tournament_type_id');

        $tournamentType = TournamentType::find($tournamentTypeId);
        if (!$tournamentType) {
            $this->error("Tournament type #{$tournamentTypeId} not found.");
            return Command::FAILURE;
        }

        $this->info("Recalculating rankings for tournament type #{$tournamentTypeId} ({$tournamentType->format_label})...");

        $controller = new MatchesController();
        $controller->recalculateRankings($tournamentTypeId);

        $this->info('Done! Rankings have been recalculated.');

        return Command::SUCCESS;
    }
}
