<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VnduprHistory;
use App\Models\UserSportScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixUserSportScores extends Command
{
    protected $signature = 'vndupr:fix-user-scores
        {--dry-run : Chi hien thi thay doi}';

    protected $description = 'Fix diem trong bang user_sport_scores dua tren vndupr_history cuoi cung.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Che do chi xem');
            $this->newLine();
        }

        $this->info('=== Fix user_sport_scores dua tren vndupr_history ===');
        $this->newLine();

        // Get all user_sport records
        $userSports = DB::table('user_sport')->get();

        $this->info("Tong cong {$userSports->count()} user_sport records");

        $fixed = 0;
        $skipped = 0;

        foreach ($userSports as $userSport) {
            $userId = $userSport->user_id;
            $sportId = $userSport->sport_id;

            // Check if user has any tournament history for this sport
            $hasTournamentHistory = VnduprHistory::where('vndupr_history.user_id', $userId)
                ->whereNotNull('vndupr_history.match_id')
                ->whereHas('match', function ($q) use ($sportId) {
                    $q->whereHas('tournamentType', function ($q2) use ($sportId) {
                        $q2->whereHas('tournament', function ($q3) use ($sportId) {
                            $q3->where('sport_id', $sportId);
                        });
                    });
                })
                ->exists();

            // If user has NO tournament history for this sport, skip
            if (!$hasTournamentHistory) {
                $skipped++;
                continue;
            }

            // Get the latest score_after from vndupr_history for this user and sport
            $lastHistory = VnduprHistory::where('vndupr_history.user_id', $userId)
                ->whereNotNull('vndupr_history.match_id')
                ->whereHas('match', function ($q) use ($sportId) {
                    $q->whereHas('tournamentType', function ($q2) use ($sportId) {
                        $q2->whereHas('tournament', function ($q3) use ($sportId) {
                            $q3->where('sport_id', $sportId);
                        });
                    });
                })
                ->orderBy('vndupr_history.updated_at', 'desc')
                ->first();

            $correctScore = $lastHistory ? (float) $lastHistory->score_after : 0;

            // Get current score from user_sport_scores
            $currentRecord = DB::table('user_sport_scores')
                ->where('user_sport_id', $userSport->id)
                ->where('score_type', 'vndupr_score')
                ->first();

            $currentScore = $currentRecord ? (float) $currentRecord->score_value : 0;

            // Check if scores match
            if (abs($currentScore - $correctScore) > 0.001) {
                $diff = $correctScore - $currentScore;
                $this->line("User #{$userId}, Sport #{$sportId}: {$currentScore} -> {$correctScore} (diff: " . sprintf('%+.3f', $diff) . ")");

                if (!$isDryRun) {
                    if ($currentRecord) {
                        DB::table('user_sport_scores')
                            ->where('id', $currentRecord->id)
                            ->update(['score_value' => $correctScore]);
                    } else {
                        DB::table('user_sport_scores')->insert([
                            'user_sport_id' => $userSport->id,
                            'score_type' => 'vndupr_score',
                            'score_value' => $correctScore,
                        ]);
                    }
                }
                $fixed++;
            } else {
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("=== Tong ket ===");
        $this->table(
            ['Loai', 'So luong'],
            [
                ['Da fix', $fixed],
                ['Dung roi', $skipped],
            ]
        );

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Khong co thay doi nao duoc luu.');
        }

        return 0;
    }
}
