<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\User;
use App\Models\VnduprHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevertGuestMatchVnduprScores extends Command
{
    protected $signature = 'vndupr:revert-guest-matches
        {--dry-run : Chi hien thi thay doi ma khong luu vao database}
        {--tournament-id= : Chi xu ly mot tournament cu the}';

    protected $description = 'Revert VNDUPR scores cho cac tran dau co guest trong tournament. Chi xu ly matches table, khong xu ly mini_matches.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tournamentId = $this->option('tournament-id');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Che do chi xem - khong luu thay doi vao database');
            $this->newLine();
        }

        $this->info('=== Bat dau revert VNDUPR cho cac tran tournament co guest ===');
        $this->newLine();

        // Step 1: Find tournament matches with guests and revert their vndupr_history
        $result = $this->processTournamentMatches($tournamentId, $isDryRun);

        // Step 2: Recalculate user_sport_scores based on corrected history
        if (!$isDryRun) {
            $this->newLine();
            $this->info('=== Recalculating user_sport_scores ===');
            $this->recalculateUserSportScores($result['affected_users']);
        }

        // Summary
        $this->newLine();
        $this->info('=== Tong ket ===');
        $this->table(
            ['Loai', 'So luong'],
            [
                ['Tournament Matches co guest', $result['match_count']],
                ['VNDUPR History Updated', $result['history_updated']],
                ['Users bi anh huong', count($result['affected_users'])],
            ]
        );

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Khong co thay doi nao duoc luu.');
        }

        return 0;
    }

    protected function processTournamentMatches(?int $tournamentId, bool $isDryRun): array
    {
        // Find matches with guests
        $matches = $this->findMatchesWithGuests($tournamentId);
        $this->info("Tim thay {$matches->count()} tran co guest");

        $affectedUsers = [];
        $historyUpdated = 0;

        foreach ($matches as $match) {
            $result = $this->revertMatchHistory($match, $isDryRun);
            if ($result['updated'] > 0) {
                $affectedUsers = array_merge($affectedUsers, $result['user_ids']);
                $historyUpdated += $result['updated'];
            }
        }

        return [
            'match_count' => $matches->count(),
            'history_updated' => $historyUpdated,
            'affected_users' => array_unique($affectedUsers),
        ];
    }

    protected function findMatchesWithGuests(?int $tournamentId)
    {
        $query = Matches::where('matches.status', 'completed')
            ->where('matches.is_bye', false)
            ->with(['homeTeam.members', 'awayTeam.members', 'vnduprHistory', 'tournamentType']);

        if ($tournamentId) {
            $query->whereHas('tournamentType', function ($q) use ($tournamentId) {
                $q->where('tournament_id', $tournamentId);
            });
        }

        $matches = $query->get();

        return $matches->filter(function ($match) {
            $homeHasGuest = $match->homeTeam->members->contains(fn($m) => $m->is_guest ?? false);
            $awayHasGuest = $match->awayTeam->members->contains(fn($m) => $m->is_guest ?? false);
            return $homeHasGuest || $awayHasGuest;
        });
    }

    protected function revertMatchHistory(Matches $match, bool $isDryRun): array
    {
        $history = $match->vnduprHistory;
        if ($history->isEmpty()) {
            return ['updated' => 0, 'user_ids' => []];
        }

        $this->line("  Match #{$match->id}");
        $updated = 0;
        $userIds = [];

        foreach ($history as $entry) {
            // Set score_after = score_before for guest matches
            if ((float) $entry->score_before !== (float) $entry->score_after) {
                $change = sprintf('%.3f -> %.3f', $entry->score_after, $entry->score_before);
                $userIds[] = $entry->user_id;

                if ($isDryRun) {
                    $this->line("    [DRY-RUN] user_id:{$entry->user_id} | {$change}");
                } else {
                    $entry->score_after = $entry->score_before;
                    $entry->save();
                    $this->line("    [UPDATED] user_id:{$entry->user_id} | {$change}");
                }
                $updated++;
            }
        }

        return ['updated' => $updated, 'user_ids' => $userIds];
    }

    protected function recalculateUserSportScores(array $userIds): void
    {
        if (empty($userIds)) {
            $this->info('Khong co user nao can recalculate.');
            return;
        }

        $this->info('Recalculating scores cho ' . count($userIds) . ' users...');

        foreach ($userIds as $userId) {
            $this->recalculateSingleUserScores($userId);
        }
    }

    protected function recalculateSingleUserScores(int $userId): void
    {
        // Get all user_sport records for this user
        $userSports = DB::table('user_sport')
            ->where('user_id', $userId)
            ->get();

        foreach ($userSports as $userSport) {
            $sportId = $userSport->sport_id;

            // Get the LAST score_after for this user/sport from vndupr_history
            // This should be the current score after all matches
            $lastHistory = VnduprHistory::where('user_id', $userId)
                ->whereHas('match', function ($q) use ($sportId) {
                    $q->whereHas('tournamentType', function ($q2) use ($sportId) {
                        $q2->whereHas('tournament', function ($q3) use ($sportId) {
                            $q3->where('sport_id', $sportId);
                        });
                    });
                })
                ->orderBy('updated_at', 'desc')
                ->first();

            $newScore = 0;
            if ($lastHistory) {
                $newScore = (float) $lastHistory->score_after;
            }

            // Update user_sport_scores
            DB::table('user_sport_scores')
                ->where('user_sport_id', $userSport->id)
                ->where('score_type', 'vndupr_score')
                ->update(['score_value' => $newScore]);

            $this->line("    User #{$userId}, Sport #{$sportId}: set to {$newScore}");
        }
    }
}
