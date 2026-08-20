<?php

namespace App\Console\Commands;

use App\Models\UserSport;
use App\Models\UserSportScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateTotalMatches extends Command
{
    protected $signature = 'matches:recalculate-total {--user_id= : Recalculate for specific user only}';
    protected $description = 'Recalculate total_matches for all user_sport records from database';

    public function handle(): int
    {
        $userId = $this->option('user_id');

        $this->info('Starting total_matches recalculation...');

        // Get all sport IDs that need recalculation
        $sportIdsQuery = UserSport::query();
        if ($userId) {
            $sportIdsQuery->where('user_id', $userId);
        }
        $sportIds = $sportIdsQuery->distinct()->pluck('sport_id');

        foreach ($sportIds as $sportId) {
            $this->recalculateForSport($sportId, $userId);
        }

        $this->info('Done!');

        return Command::SUCCESS;
    }

    private function recalculateForSport(int $sportId, ?int $userId = null): void
    {
        $this->info("Processing sport_id: {$sportId}");

        // Get all user_sport records for this sport
        $query = UserSport::where('sport_id', $sportId);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $userSports = $query->get();

        if ($userSports->isEmpty()) {
            $this->info("  No user_sport records found for sport_id: {$sportId}");
            return;
        }

        $this->info("  Found {$userSports->count()} user_sport records");

        $bar = $this->output->createProgressBar($userSports->count());
        $bar->start();

        $updated = 0;

        foreach ($userSports as $userSport) {
            $totalMatches = $this->countTotalMatches($userSport->user_id, $sportId);
            $userSport->update(['total_matches' => $totalMatches]);
            $updated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Updated {$updated} records for sport_id: {$sportId}");
    }

    private function countTotalMatches(int $userId, int $sportId): int
    {
        // Tournament matches
        $tournamentMatches = DB::select("
            SELECT COUNT(DISTINCT m.id) as cnt
            FROM matches m
            JOIN tournament_types tt ON m.tournament_type_id = tt.id
            JOIN tournaments t ON tt.tournament_id = t.id
            JOIN team_members tm ON tm.team_id IN (m.home_team_id, m.away_team_id)
            WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed' AND m.is_bye = 0
        ", [$userId, $sportId])[0]->cnt ?? 0;

        // Mini tournament matches
        $miniMatches = DB::select("
            SELECT COUNT(DISTINCT mm.id) as cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN (
                SELECT mini_team_id, user_id FROM mini_team_members
                UNION
                SELECT id as mini_team_id, user_id FROM mini_participants WHERE user_id IS NOT NULL
            ) participants ON (
                participants.mini_team_id = mm.team1_id OR
                participants.mini_team_id = mm.team2_id OR
                participants.user_id IN (mm.participant1_id, mm.participant2_id)
            )
            WHERE participants.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed'
        ", [$userId, $sportId])[0]->cnt ?? 0;

        // Quick match (match_histories)
        $qmMatches = DB::select("
            SELECT COUNT(DISTINCT mh.quick_match_id) as cnt
            FROM match_histories mh
            JOIN quick_matches qm ON mh.quick_match_id = qm.id
            LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
            LEFT JOIN user_sport usc ON qm.created_by = usc.user_id
            WHERE mh.user_id = ?
              AND qm.status = 'completed'
              AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))
        ", [$userId, $sportId, $sportId])[0]->cnt ?? 0;

        return (int) $tournamentMatches + (int) $miniMatches + (int) $qmMatches;
    }
}
