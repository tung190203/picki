<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Services\MiniTournamentService;
use Illuminate\Console\Command;

class RolloverMiniTournamentRecurrenceCommand extends Command
{
    protected $signature = 'mini-tournaments:rollover-recurrence
                            {--dry-run : Only report what would be created}';

    protected $description = 'Create next period occurrences for recurring mini tournament series';

    public function handle(MiniTournamentService $tournamentService): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry run – no changes will be made.');
        }

        // Process latest occurrence of each active recurring series only.
        $seriesSeeds = MiniTournament::whereNotNull('recurring_schedule')
            ->whereNotNull('recurrence_series_id')
            ->whereNull('recurrence_series_cancelled_at')
            ->with(['miniTournamentStaffs', 'participants'])
            ->orderBy('recurrence_series_id')
            ->orderByDesc('start_time')
            ->get();

        $recurringTournaments = $seriesSeeds
            ->groupBy('recurrence_series_id')
            ->map(fn ($items) => $items->first())
            ->values();

        $created = 0;

        foreach ($recurringTournaments as $tournament) {
            // Check if we need to create new occurrences
            $nextOccurrence = $tournament->calculateNextOccurrence();

            if ($nextOccurrence) {
                $organizerId = $this->resolveOrganizerId($tournament);
                if (!$organizerId) {
                    $this->warn("Skip series {$tournament->recurrence_series_id}: cannot resolve organizer.");
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $createdOccurrence = $tournamentService->createNextOccurrenceIfMissing(
                        $tournament,
                        $nextOccurrence,
                        $organizerId,
                        $tournament->recurrence_series_id
                    );
                    if (!$createdOccurrence) {
                        continue;
                    }
                }

                $created++;
                $this->line("Created occurrence for tournament {$tournament->id} at {$nextOccurrence->toDateTimeString()}");
            }
        }

        $this->info("Rollover complete. Created {$created} new occurrence(s).");

        return self::SUCCESS;
    }

    private function resolveOrganizerId(MiniTournament $tournament): ?int
    {
        $organizerStaff = $tournament->miniTournamentStaffs
            ->firstWhere('role', MiniTournamentStaff::ROLE_ORGANIZER);

        if ($organizerStaff?->user_id) {
            return (int) $organizerStaff->user_id;
        }

        $firstParticipant = $tournament->participants->first();
        if ($firstParticipant?->user_id) {
            return (int) $firstParticipant->user_id;
        }

        return null;
    }
}
