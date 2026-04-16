<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\User;
use App\Models\Tournament;
use App\Services\TournamentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillTournamentEndDate extends Command
{
    protected $signature = 'tournaments:backfill-end-date';

    protected $description = 'Backfill end_date/end_time cho tournaments/mini-tournaments từ start_date/start_time + duration, sau đó auto-close giải đã hết hạn';

    public function __construct(
        private TournamentService $tournamentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('=== Bat dau backfill end_date/end_time ===');
        $this->newLine();

        $this->processTournaments();
        $this->newLine();
        $this->processMiniTournaments();

        $this->newLine();
        $this->info('=== Hoan tat ===');
        return 0;
    }

    protected function processTournaments(): void
    {
        $this->info('--- Tournaments ---');

        $tournaments = Tournament::where('status', '!=', Tournament::CLOSED)
            ->whereNull('end_date')
            ->whereNotNull('start_date')
            ->whereNotNull('duration')
            ->get();

        if ($tournaments->isEmpty()) {
            $this->info('Khong co tournament nao can backfill end_date.');
            return;
        }

        $backfilledCount = 0;
        $closedCount = 0;

        foreach ($tournaments as $tournament) {
            $start = $tournament->start_date instanceof Carbon
                ? $tournament->start_date->copy()
                : Carbon::parse($tournament->start_date);

            $tournament->end_date = $start->copy()->addMinutes($tournament->duration);

            if ($tournament->end_date < now()) {
                $this->tournamentService->closeTournament($tournament);
                $closedCount++;
                $this->line("  [AUTO-CLOSE] #{$tournament->id} '{$tournament->name}' (end_date: {$tournament->end_date->toDateString()})");
            } else {
                $tournament->save();
                $this->line("  [BACKFILL]   #{$tournament->id} '{$tournament->name}' (end_date: {$tournament->end_date->toDateString()})");
            }

            $backfilledCount++;
        }

        $this->info("Tournaments: da backfill {$backfilledCount}, da dong {$closedCount}.");
    }

    protected function processMiniTournaments(): void
    {
        $this->info('--- Mini-Tournaments ---');

        $miniTournaments = MiniTournament::where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->whereNull('end_time')
            ->whereNotNull('start_time')
            ->whereNotNull('duration')
            ->with('matches')
            ->get();

        if ($miniTournaments->isEmpty()) {
            $this->info('Khong co mini-tournament nao can backfill end_time.');
            return;
        }

        $backfilledCount = 0;
        $closedCount = 0;

        foreach ($miniTournaments as $mini) {
            $start = $mini->start_time instanceof Carbon
                ? $mini->start_time->copy()
                : Carbon::parse($mini->start_time);

            $mini->end_time = $start->copy()->addMinutes($mini->duration);

            $shouldClose = $mini->end_time < now();

            if ($shouldClose) {
                $totalMatches = $mini->matches->count();
                $completedMatches = $mini->matches->where('status', 'completed')->count();

                if ($totalMatches === 0 || $totalMatches === $completedMatches) {
                    $this->closeMiniTournament($mini);
                    $closedCount++;
                    $this->line("  [AUTO-CLOSE] #{$mini->id} '{$mini->name}' (end_time: {$mini->end_time->toDateTimeString()})");
                } else {
                    $mini->save();
                    $this->line("  [BACKFILL]   #{$mini->id} '{$mini->name}' (end_time: {$mini->end_time->toDateTimeString()}) - chua dong vi co match chua completed ({$completedMatches}/{$totalMatches})");
                }
            } else {
                $mini->save();
                $this->line("  [BACKFILL]   #{$mini->id} '{$mini->name}' (end_time: {$mini->end_time->toDateTimeString()})");
            }

            $backfilledCount++;
        }

        $this->info("Mini-Tournaments: da backfill {$backfilledCount}, da dong {$closedCount}.");
    }

    protected function closeMiniTournament(MiniTournament $miniTournament): void
    {
        $miniTournament->status = MiniTournament::STATUS_CLOSED;
        $miniTournament->save();

        $miniTournament->loadMissing('participants');

        foreach ($miniTournament->participants as $participant) {
            if (!$participant->user_id || $participant->is_guest) {
                continue;
            }

            $sportId = $miniTournament->sport_id;
            $userId = $participant->user_id;

            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            $currentRating = $user->vnduprScoresBySport($sportId)->max('score_value');
            $currentRank = $user->getVNRank($sportId);

            $participant->rating_before = $currentRating;
            $participant->rating_after = $currentRating;
            $participant->rank_before = $currentRank;
            $participant->rank_after = $currentRank;
            $participant->rank_change = null;
            $participant->save();
        }
    }
}
