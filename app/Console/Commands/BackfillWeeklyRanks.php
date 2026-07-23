<?php

namespace App\Console\Commands;

use App\Models\Sport;
use App\Models\User;
use App\Models\WeeklyRank;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillWeeklyRanks extends Command
{
    protected $signature = 'ranks:backfill-weekly {--weeks=4 : Number of weeks to backfill}';

    protected $description = 'Backfill weekly ranks for historical Sundays';

    public function handle(): int
    {
        $weeks = (int) $this->option('weeks');
        $this->info("Backfilling weekly ranks for the last {$weeks} weeks...");

        $sport = Sport::where('slug', 'pickleball')->first();
        if (!$sport) {
            $this->error('Pickleball sport not found.');
            return Command::FAILURE;
        }

        $sportId = $sport->id;
        $rankingMatches = (int) \App\Models\SystemSetting::where('key', 'ranking_matches')->first()?->value ?: 10;
        $excludedEmail = 'vrplus2018@gmail.com';

        // Get all ranked users
        $rankedUsers = DB::table('user_sport')
            ->join('users', 'users.id', '=', 'user_sport.user_id')
            ->join('user_sport_scores', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->where('user_sport.total_matches', '>=', $rankingMatches)
            ->where('users.email', '!=', $excludedEmail)
            ->groupBy('user_sport.user_id')
            ->select('user_sport.user_id')
            ->get();

        $userIds = $rankedUsers->pluck('user_id')->toArray();

        if (empty($userIds)) {
            $this->info('No ranked users found.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($weeks);
        $bar->start();

        for ($i = 1; $i <= $weeks; $i++) {
            $targetSunday = Carbon::now()->startOfWeek(Carbon::SUNDAY)->subWeeks($i)->endOfDay();

            // Check if we already have data for this Sunday
            $existing = WeeklyRank::where('sport_id', $sportId)
                ->whereNotNull('recorded_at')
                ->whereDate('recorded_at', $targetSunday->toDateString())
                ->exists();

            if ($existing) {
                $this->line(" (skipped - data exists for {$targetSunday->toDateString()})");
                $bar->advance();
                continue;
            }

            $ranks = User::getBatchVNRanks($userIds, $sportId);

            $records = [];
            foreach ($ranks as $userId => $rank) {
                if ($rank !== null) {
                    $records[] = [
                        'user_id' => $userId,
                        'sport_id' => $sportId,
                        'rank' => $rank,
                        'recorded_at' => $targetSunday,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($records)) {
                WeeklyRank::insert($records);
            }

            $this->line(" (backfilled " . count($records) . " records for {$targetSunday->toDateString()})");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Backfill completed.');

        return Command::SUCCESS;
    }
}
