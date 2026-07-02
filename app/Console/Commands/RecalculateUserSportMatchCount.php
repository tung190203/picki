<?php

namespace App\Console\Commands;

use App\Models\UserSport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateUserSportMatchCount extends Command
{
    protected $signature = 'user-sport:recalculate-matches {--sport-id= : Recalculate only for specific sport ID}';
    protected $description = 'Recalculate total_matches for all user_sport records based on actual completed matches';

    public function handle(): int
    {
        $sportId = $this->option('sport-id');

        $this->info('Recalculating total_matches on user_sport...');
        $this->info('This may take a while for large datasets.');

        $query = UserSport::query();
        if ($sportId) {
            $query->where('sport_id', $sportId);
        }

        $total = $query->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $query->chunkById(500, function ($records) use (&$updated, $bar) {
            foreach ($records as $userSport) {
                $count = $this->computeTotalMatches($userSport->user_id, $userSport->sport_id);
                $userSport->updateQuietly(['total_matches' => $count]);
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated {$updated} user_sport records.");

        return Command::SUCCESS;
    }

    protected function computeTotalMatches(int $userId, int $sportId): int
    {
        // ─── Tournament matches (team-based, home + away) ───
        $tHome = (int) DB::selectOne("
            SELECT COUNT(DISTINCT m.id) AS cnt
            FROM matches m
            JOIN tournament_types tt ON m.tournament_type_id = tt.id
            JOIN tournaments t ON tt.tournament_id = t.id
            JOIN team_members tm ON tm.team_id = m.home_team_id
            WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed' AND m.is_bye = 0
        ", [$userId, $sportId])?->cnt ?? 0;

        $tAway = (int) DB::selectOne("
            SELECT COUNT(DISTINCT m.id) AS cnt
            FROM matches m
            JOIN tournament_types tt ON m.tournament_type_id = tt.id
            JOIN tournaments t ON tt.tournament_id = t.id
            JOIN team_members tm ON tm.team_id = m.away_team_id
            WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed' AND m.is_bye = 0
        ", [$userId, $sportId])?->cnt ?? 0;

        $tournamentMatches = $tHome + $tAway;

        // ─── Mini tournament matches (team-based, side1 + side2) ───
        $mSide1 = (int) DB::selectOne("
            SELECT COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
            WHERE mtm.user_id = ?
              AND mnt.sport_id = ? AND mm.status = 'completed'
              AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
        ", [$userId, $sportId])?->cnt ?? 0;

        $mSide2 = (int) DB::selectOne("
            SELECT COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
            WHERE mtm.user_id = ?
              AND mnt.sport_id = ? AND mm.status = 'completed'
              AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
        ", [$userId, $sportId])?->cnt ?? 0;

        // ─── Mini tournament solo participants ───
        $mSolo = (int) DB::selectOne("
            SELECT COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_participants mp ON mp.mini_tournament_id = mnt.id
            WHERE mp.user_id = ?
              AND (mm.participant1_id = mp.id OR mm.participant2_id = mp.id)
              AND mnt.sport_id = ? AND mm.status = 'completed'
              AND mm.team1_id IS NULL AND mm.team2_id IS NULL
        ", [$userId, $sportId])?->cnt ?? 0;

        $miniMatches = $mSide1 + $mSide2 + $mSolo;

        // ─── Quick matches (count distinct quick_match_id per user) ───
        $quickMatches = (int) DB::selectOne("
            SELECT COUNT(DISTINCT mh.quick_match_id) AS cnt
            FROM match_histories mh
            JOIN quick_matches qm ON mh.quick_match_id = qm.id
            LEFT JOIN competition_location_sport cls ON qm.competition_location_id = cls.competition_location_id
            LEFT JOIN user_sport usc ON qm.created_by = usc.user_id
            WHERE mh.user_id = ?
              AND qm.status = 'completed'
              AND (cls.sport_id = ? OR (qm.competition_location_id IS NULL AND usc.sport_id = ?))
        ", [$userId, $sportId, $sportId])?->cnt ?? 0;

        return $tournamentMatches + $miniMatches + $quickMatches;
    }
}
