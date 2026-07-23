<?php

namespace App\Console\Commands;

use App\Models\Sport;
use App\Models\User;
use App\Models\WeeklyRank;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotWeeklyRanks extends Command
{
    protected $signature = 'ranks:snapshot-weekly';

    protected $description = 'Snapshot current ranks to weekly_ranks table for weekly_change tracking';

    public function handle(): int
    {
        $this->info('Starting weekly rank snapshot...');

        $sport = Sport::where('slug', 'pickleball')->first();
        if (!$sport) {
            $this->error('Pickleball sport not found.');
            return Command::FAILURE;
        }

        $sportId = $sport->id;
        $lastSunday = Carbon::now()->startOfWeek(Carbon::SUNDAY)->subWeek()->endOfDay();

        // Archive current snapshot: set recorded_at on existing NULL records
        WeeklyRank::where('sport_id', $sportId)
            ->whereNull('recorded_at')
            ->update(['recorded_at' => $lastSunday]);

        $this->info("Archived previous snapshot with recorded_at = {$lastSunday}");

        // Get all ranked users
        $rankingMatches = (int) \App\Models\SystemSetting::where('key', 'ranking_matches')->first()?->value ?: 10;
        $excludedEmail = 'vrplus2018@gmail.com';

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

        // Get current ranks using the existing ranking logic
        $ranks = User::getBatchVNRanks($userIds, $sportId);

        $records = [];
        $now = now();
        foreach ($ranks as $userId => $rank) {
            if ($rank !== null) {
                $records[] = [
                    'user_id' => $userId,
                    'sport_id' => $sportId,
                    'rank' => $rank,
                    'recorded_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($records)) {
            WeeklyRank::insert($records);
            $this->info("Inserted " . count($records) . " current rank records.");
        }

        $this->info('Weekly rank snapshot completed.');
        return Command::SUCCESS;
    }
}
