<?php

namespace App\Console\Commands;

use App\Models\UserSport;
use App\Models\UserSportScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeUserSports extends Command
{
    protected $signature = 'users:init-sports
        {--sport-id=1 : Sport ID to initialize}
        {--min-score=1 : Minimum vndupr score value}
        {--dry-run : Show what would be done without making changes}';

    protected $description = 'Initialize sport and vndupr_score for users who do not have sport or have score < min-score';

    public function handle(): int
    {
        $sportId = (int) $this->option('sport-id');
        $minScore = (float) $this->option('min-score');
        $dryRun = $this->option('dry-run');

        $this->info("Initializing users with sport_id={$sportId} and vndupr_score >= {$minScore}...");
        $this->info("Searching for users who:");
        $this->info("  1. Do NOT have this sport in user_sport table, OR");
        $this->info("  2. Have this sport but WITHOUT vndupr_score or score < {$minScore}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made.');
        }

        // Query 1: Users who don't have this sport at all
        $usersWithoutSport = DB::table('users')
            ->whereNotExists(function ($query) use ($sportId) {
                $query->select(DB::raw(1))
                    ->from('user_sport')
                    ->whereColumn('user_id', 'users.id')
                    ->where('sport_id', $sportId);
            })
            ->where('is_banned', false)
            ->whereNull('deleted_at')
            ->select('id', 'full_name', 'email')
            ->get();

        // Query 2: Users who have sport but no vndupr_score or score < minScore
        $usersWithSportNoScore = DB::table('user_sport as us')
            ->join('users', 'users.id', '=', 'us.user_id')
            ->leftJoin('user_sport_scores as uss', function ($join) use ($sportId) {
                $join->on('uss.user_sport_id', '=', 'us.id')
                    ->where('uss.score_type', 'vndupr_score');
            })
            ->where('us.sport_id', $sportId)
            ->where('users.is_banned', false)
            ->whereNull('users.deleted_at')
            ->where(function ($query) use ($minScore) {
                $query->whereNull('uss.id')
                    ->orWhere('uss.score_value', '<', $minScore);
            })
            ->select('us.id as user_sport_id', 'users.id as user_id', 'users.full_name', 'users.email', 'uss.score_value')
            ->get();

        $countWithoutSport = $usersWithoutSport->count();
        $countWithSportNoScore = $usersWithSportNoScore->count();
        $totalCount = $countWithoutSport + $countWithSportNoScore;

        $this->info("");
        $this->info("Found:");
        $this->info("  - {$countWithoutSport} users WITHOUT sport_id={$sportId}");
        $this->info("  - {$countWithSportNoScore} users WITH sport but MISSING or LOW vndupr_score");

        if ($totalCount === 0) {
            $this->info('All users already have valid sport and vndupr_score.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info("=== Users WITHOUT sport (will create sport + score) ===");
            if ($countWithoutSport > 0) {
                $this->table(['ID', 'Name', 'Email'], $usersWithoutSport->take(10)->map(fn($u) => [$u->id, $u->full_name, $u->email]));
                if ($countWithoutSport > 10) {
                    $this->info("... and " . ($countWithoutSport - 10) . " more.");
                }
            } else {
                $this->info("None.");
            }

            $this->newLine();
            $this->info("=== Users WITH sport but MISSING or LOW vndupr_score (will add/update score) ===");
            if ($countWithSportNoScore > 0) {
                $this->table(['UserSport ID', 'User ID', 'Name', 'Email', 'Current Score'], 
                    $usersWithSportNoScore->take(10)->map(fn($u) => [
                        $u->user_sport_id, 
                        $u->user_id, 
                        $u->full_name, 
                        $u->email, 
                        $u->score_value ?? 'NULL'
                    ]));
                if ($countWithSportNoScore > 10) {
                    $this->info("... and " . ($countWithSportNoScore - 10) . " more.");
                }
            } else {
                $this->info("None.");
            }

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($usersWithoutSport, $usersWithSportNoScore, $sportId, $minScore, $bar, &$created, &$updated, &$skipped) {
            // Process users without sport
            foreach ($usersWithoutSport as $user) {
                $exists = UserSport::where('user_id', $user->id)
                    ->where('sport_id', $sportId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $userSport = UserSport::create([
                    'user_id' => $user->id,
                    'sport_id' => $sportId,
                    'tier' => null,
                    'total_matches' => 0,
                ]);

                UserSportScore::create([
                    'user_sport_id' => $userSport->id,
                    'score_type' => 'vndupr_score',
                    'score_value' => $minScore,
                ]);

                $created++;
                $bar->advance();
            }

            // Process users with sport but no/low score
            foreach ($usersWithSportNoScore as $record) {
                if ($record->score_value === null) {
                    // No score exists - create new
                    UserSportScore::create([
                        'user_sport_id' => $record->user_sport_id,
                        'score_type' => 'vndupr_score',
                        'score_value' => $minScore,
                    ]);
                    $created++;
                } else {
                    // Has score but < minScore - update to minScore
                    UserSportScore::where('id', function ($query) use ($record) {
                        $query->select('id')
                            ->from('user_sport_scores')
                            ->where('user_sport_id', $record->user_sport_id)
                            ->where('score_type', 'vndupr_score')
                            ->orderByDesc('score_value')
                            ->limit(1);
                    })->update(['score_value' => $minScore]);
                    $updated++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Completed:");
        $this->info("  - {$created} records created");
        $this->info("  - {$updated} scores updated");
        $this->info("  - {$skipped} users skipped");

        return Command::SUCCESS;
    }
}
