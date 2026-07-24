<?php

namespace App\Console\Commands;

use App\Enums\BadgeType;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\BadgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedChampionBadges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'badges:seed-champions 
                            {--dry-run : Show what would be awarded without making changes}
                            {--tournament= : Only process specific tournament ID}
                            {--force : Re-award badges even if already awarded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Award CHAMPION badges to users who won tournaments (based on team_rankings)';

    /**
     * Execute the console command.
     */
    public function handle(BadgeService $badgeService): int
    {
        $dryRun = $this->option('dry-run');
        $tournamentId = $this->option('tournament');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('🏆 Starting Champion Badge Seeder...');
        $this->newLine();

        // Query closed tournaments
        $query = Tournament::query()
            ->where('status', Tournament::CLOSED);

        if ($tournamentId) {
            $query->where('id', $tournamentId);
        }

        $tournaments = $query->get();

        if ($tournaments->isEmpty()) {
            $this->warn('No closed tournaments found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tournaments->count()} closed tournament(s)");
        $this->newLine();

        $totalChampionsAwarded = 0;
        $totalAlreadyAwarded = 0;
        $totalNoRanking = 0;

        foreach ($tournaments as $tournament) {
            $result = $this->processTournament($tournament, $badgeService, $dryRun, $force);
            
            $totalChampionsAwarded += $result['awarded'];
            $totalAlreadyAwarded += $result['already_awarded'];
            $totalNoRanking += $result['no_ranking'];
        }

        // Summary
        $this->newLine();
        $this->info('📊 SUMMARY');
        $this->line('─' . str_repeat('─', 50));
        $this->line("Tournaments processed: {$tournaments->count()}");
        
        if ($dryRun) {
            $this->line("Champions that WOULD be awarded: {$totalChampionsAwarded}");
            $this->line("Champions already have badge: {$totalAlreadyAwarded}");
        } else {
            $this->line("Champions awarded: {$totalChampionsAwarded}");
            $this->line("Champions already had badge: {$totalAlreadyAwarded}");
        }
        
        if ($totalNoRanking > 0) {
            $this->warn("Tournaments with no ranking data: {$totalNoRanking}");
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  This was a dry run. Run without --dry-run to actually award badges.');
        } else {
            $this->info('✅ Champion badge seeding completed!');
        }

        return Command::SUCCESS;
    }

    /**
     * Process a single tournament and award champion badges.
     * 
     * Uses the same logic as /tournaments/{id}/leaderboard API:
     * - Get tournament_type_ids from tournament
     * - Get teams with rank = 1 from team_rankings
     * - Award badges to all members of winning teams
     */
    protected function processTournament(
        Tournament $tournament,
        BadgeService $badgeService,
        bool $dryRun,
        bool $force
    ): array {
        $result = [
            'awarded' => 0,
            'already_awarded' => 0,
            'no_ranking' => 0,
        ];

        // Get tournament type IDs for this tournament
        $tournamentTypeIds = TournamentType::where('tournament_id', $tournament->id)->pluck('id');

        if ($tournamentTypeIds->isEmpty()) {
            $this->line("  ⚠️  Tournament #{$tournament->id} ({$tournament->name}): No tournament types found");
            $result['no_ranking']++;
            return $result;
        }

        // Get teams with rank = 1 (winners) from team_rankings
        // This is the same logic as the leaderboard API
        $winnerRankings = TeamRanking::with(['team.members'])
            ->whereIn('tournament_type_id', $tournamentTypeIds)
            ->where('rank', 1)
            ->get();

        if ($winnerRankings->isEmpty()) {
            $this->line("  ⚠️  Tournament #{$tournament->id} ({$tournament->name}): No ranking data (rank=1 teams)");
            $result['no_ranking']++;
            return $result;
        }

        $this->newLine();
        $this->line("  🏆 Tournament: {$tournament->name} (ID: {$tournament->id})");

        foreach ($winnerRankings as $ranking) {
            $team = $ranking->team;

            if (!$team) {
                continue;
            }

            $members = $team->members;

            if ($members->isEmpty()) {
                $this->line("    ⚠️  Team: {$team->name} (ID: {$team->id}) - No members");
                continue;
            }

            $this->line("    👑 Winner Team: {$team->name} (ID: {$team->id})");

            foreach ($members as $member) {
                $alreadyHasBadge = $badgeService->hasBadge($member->id, BadgeType::CHAMPION);

                if ($alreadyHasBadge && !$force) {
                    $this->line("      ✓ {$member->full_name} (ID: {$member->id}) - Already has CHAMPION badge");
                    $result['already_awarded']++;
                } else {
                    if ($dryRun) {
                        $this->line("      🎯 {$member->full_name} (ID: {$member->id}) - WOULD be awarded CHAMPION badge");
                    } else {
                        $badgeService->grant_champion($member->id);
                        $this->line("      ✅ {$member->full_name} (ID: {$member->id}) - Awarded CHAMPION badge");
                    }
                    $result['awarded']++;
                }
            }
        }

        return $result;
    }
}
