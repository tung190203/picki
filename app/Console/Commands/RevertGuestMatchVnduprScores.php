<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTeamMember;
use App\Models\QuickMatch;
use App\Models\User;
use App\Models\VnduprHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevertGuestMatchVnduprScores extends Command
{
    protected $signature = 'vndupr:revert-guest-matches
        {--dry-run : Chỉ hiển thị thay đổi mà không lưu vào database}
        {--tournament-id= : Chỉ xử lý một tournament cụ thể}
        {--mini-tournament-id= : Chỉ xử lý một mini tournament cụ thể}';

    protected $description = 'Revert VNDUPR scores cho các trận đấu có guest. Guest matches sẽ giữ nguyên điểm (score_after = score_before).';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tournamentId = $this->option('tournament-id');
        $miniTournamentId = $this->option('mini-tournament-id');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Che do chi xem - khong luu thay doi vao database');
            $this->newLine();
        }

        $this->info('=== Bat dau revert VNDUPR cho cac tran dau co guest ===');
        $this->newLine();

        $affectedUsers = [];
        $stats = [
            'tournament_matches' => 0,
            'mini_matches' => 0,
            'quick_matches' => 0,
            'history_updated' => 0,
            'scores_recalculated' => 0,
        ];

        // 1. PROCESS TOURNAMENT MATCHES
        if (!$miniTournamentId) {
            $tournamentMatches = $this->findTournamentMatchesWithGuests($tournamentId);
            $this->info("Tim thay {$tournamentMatches->count()} tran tournament co guest");

            foreach ($tournamentMatches as $match) {
                $result = $this->processTournamentMatch($match, $isDryRun);
                if ($result['affected']) {
                    $stats['tournament_matches']++;
                    $stats['history_updated'] += $result['history_count'];
                    $affectedUsers = array_merge($affectedUsers, $result['affected_users']);
                }
            }
        }

        // 2. PROCESS MINI TOURNAMENT MATCHES
        if (!$tournamentId) {
            $miniMatches = $this->findMiniMatchesWithGuests($miniTournamentId);
            $this->info("Tim thay {$miniMatches->count()} tran mini-tournament co guest");

            foreach ($miniMatches as $match) {
                $result = $this->processMiniMatch($match, $isDryRun);
                if ($result['affected']) {
                    $stats['mini_matches']++;
                    $stats['history_updated'] += $result['history_count'];
                    $affectedUsers = array_merge($affectedUsers, $result['affected_users']);
                }
            }
        }

        // 3. PROCESS QUICK MATCHES
        if (!$tournamentId && !$miniTournamentId) {
            $quickMatches = $this->findQuickMatchesWithGuests();
            $this->info("Tim thay {$quickMatches->count()} tran quick match co guest");

            foreach ($quickMatches as $match) {
                $result = $this->processQuickMatch($match, $isDryRun);
                if ($result['affected']) {
                    $stats['quick_matches']++;
                    $stats['history_updated'] += $result['history_count'];
                    $affectedUsers = array_merge($affectedUsers, $result['affected_users']);
                }
            }
        }

        // 4. RECALCULATE SCORES FOR AFFECTED USERS
        $affectedUsers = array_unique($affectedUsers);
        $this->newLine();
        $this->info('=== Recalculate scores cho ' . count($affectedUsers) . ' users bi anh huong ===');

        if (!empty($affectedUsers) && !$isDryRun) {
            $stats['scores_recalculated'] = $this->recalculateScoresForUsers($affectedUsers);
        }

        // SUMMARY
        $this->newLine();
        $this->info('=== Tong ket ===');
        $this->table(
            ['Loai', 'So tran'],
            [
                ['Tournament Matches', $stats['tournament_matches']],
                ['Mini Tournament Matches', $stats['mini_matches']],
                ['Quick Matches', $stats['quick_matches']],
                ['VNDUPR History Updated', $stats['history_updated']],
                ['Scores Recalculated', $stats['scores_recalculated']],
                ['Users Affected', count($affectedUsers)],
            ]
        );

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Khong co thay doi nao duoc luu. Chay lai ma khong co --dry-run de ap dung.');
        }

        return 0;
    }

    protected function findTournamentMatchesWithGuests(?int $tournamentId = null)
    {
        $query = Matches::where('status', 'completed')
            ->where('is_bye', false)
            ->with(['homeTeam.members', 'awayTeam.members', 'vnduprHistory']);

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

    protected function findMiniMatchesWithGuests(?int $miniTournamentId = null)
    {
        $query = MiniMatch::where('status', MiniMatch::STATUS_COMPLETED)
            ->where('is_bye', false)
            ->with(['team1.members', 'team2.members', 'vnduprHistory']);

        if ($miniTournamentId) {
            $query->where('mini_tournament_id', $miniTournamentId);
        }

        $matches = $query->get();

        return $matches->filter(function ($match) {
            $team1HasGuest = $match->team1 && $match->team1->members->contains(fn($m) => $m->is_guest ?? false);
            $team2HasGuest = $match->team2 && $match->team2->members->contains(fn($m) => $m->is_guest ?? false);
            return $team1HasGuest || $team2HasGuest;
        });
    }

    protected function findQuickMatchesWithGuests()
    {
        $quickMatchIds = VnduprHistory::whereNotNull('quick_match_id')
            ->pluck('quick_match_id')
            ->unique();

        return QuickMatch::whereIn('id', $quickMatchIds)
            ->where('status', 'completed')
            ->with('vnduprHistory')
            ->get()
            ->filter(function ($match) {
                $teamA = is_array($match->team_a ?? null) ? $match->team_a : json_decode($match->team_a ?? '[]', true) ?? [];
                $teamB = is_array($match->team_b ?? null) ? $match->team_b : json_decode($match->team_b ?? '[]', true) ?? [];
                $allUserIds = array_merge($teamA, $teamB);

                if (empty($allUserIds)) {
                    return false;
                }

                return User::whereIn('id', $allUserIds)
                    ->where('is_guest', true)
                    ->exists();
            });
    }

    protected function processTournamentMatch(Matches $match, bool $isDryRun): array
    {
        $history = $match->vnduprHistory;
        if ($history->isEmpty()) {
            return ['affected' => false, 'history_count' => 0, 'affected_users' => []];
        }

        $this->line("  [TOURNAMENT] Match #{$match->id}");
        $affectedUsers = [];
        $count = 0;

        foreach ($history as $entry) {
            if ((float) $entry->score_before !== (float) $entry->score_after) {
                $change = sprintf('%.3f -> %.3f', $entry->score_after, $entry->score_before);
                $affectedUsers[] = $entry->user_id;

                if ($isDryRun) {
                    $this->line("    [DRY-RUN] user_id:{$entry->user_id} | {$change}");
                } else {
                    $entry->score_after = $entry->score_before;
                    $entry->save();
                    $this->line("    [UPDATED] user_id:{$entry->user_id} | {$change}");
                }
                $count++;
            }
        }

        return [
            'affected' => $count > 0,
            'history_count' => $count,
            'affected_users' => $affectedUsers,
        ];
    }

    protected function processMiniMatch(MiniMatch $match, bool $isDryRun): array
    {
        $history = $match->vnduprHistory;
        if ($history->isEmpty()) {
            return ['affected' => false, 'history_count' => 0, 'affected_users' => []];
        }

        $this->line("  [MINI] MiniMatch #{$match->id} | Tournament #{$match->mini_tournament_id}");
        $affectedUsers = [];
        $count = 0;

        foreach ($history as $entry) {
            if ((float) $entry->score_before !== (float) $entry->score_after) {
                $change = sprintf('%.3f -> %.3f', $entry->score_after, $entry->score_before);
                $affectedUsers[] = $entry->user_id;

                if ($isDryRun) {
                    $this->line("    [DRY-RUN] user_id:{$entry->user_id} | {$change}");
                } else {
                    $entry->score_after = $entry->score_before;
                    $entry->save();
                    $this->line("    [UPDATED] user_id:{$entry->user_id} | {$change}");
                }
                $count++;
            }
        }

        return [
            'affected' => $count > 0,
            'history_count' => $count,
            'affected_users' => $affectedUsers,
        ];
    }

    protected function processQuickMatch(QuickMatch $match, bool $isDryRun): array
    {
        $history = $match->vnduprHistory;
        if ($history->isEmpty()) {
            return ['affected' => false, 'history_count' => 0, 'affected_users' => []];
        }

        $this->line("  [QUICK] QuickMatch #{$match->id}");
        $affectedUsers = [];
        $count = 0;

        foreach ($history as $entry) {
            if ((float) $entry->score_before !== (float) $entry->score_after) {
                $change = sprintf('%.3f -> %.3f', $entry->score_after, $entry->score_before);
                $affectedUsers[] = $entry->user_id;

                if ($isDryRun) {
                    $this->line("    [DRY-RUN] user_id:{$entry->user_id} | {$change}");
                } else {
                    $entry->score_after = $entry->score_before;
                    $entry->save();
                    $this->line("    [UPDATED] user_id:{$entry->user_id} | {$change}");
                }
                $count++;
            }
        }

        return [
            'affected' => $count > 0,
            'history_count' => $count,
            'affected_users' => $affectedUsers,
        ];
    }

    protected function recalculateScoresForUsers(array $userIds): int
    {
        $this->info('Recalculating scores for ' . count($userIds) . ' users...');

        $updated = 0;

        foreach ($userIds as $userId) {
            $this->recalculateUserScores($userId);
            $updated++;
        }

        return $updated;
    }

    protected function recalculateUserScores(int $userId): void
    {
        $userSports = DB::table('user_sport')
            ->where('user_id', $userId)
            ->get();

        foreach ($userSports as $userSport) {
            $sportId = $userSport->sport_id;
            $validMatches = $this->getValidMatchIdsForUser($userId, $sportId);

            if ($validMatches->isEmpty()) {
                DB::table('user_sport_scores')
                    ->where('user_sport_id', $userSport->id)
                    ->where('score_type', 'vndupr_score')
                    ->update(['score_value' => 0]);
                continue;
            }

            $newScore = $this->calculateScoreFromMatches($userId, $sportId, $validMatches);

            DB::table('user_sport_scores')
                ->where('user_sport_id', $userSport->id)
                ->where('score_type', 'vndupr_score')
                ->update(['score_value' => $newScore]);
        }

        $this->line("    Recalculated scores for user #{$userId}");
    }

    protected function getValidMatchIdsForUser(int $userId, int $sportId): \Illuminate\Support\Collection
    {
        $matchIds = collect();

        // Get all tournament IDs for this sport
        $tournamentIds = DB::table('tournaments')
            ->where('sport_id', $sportId)
            ->pluck('id')
            ->toArray();

        // Tournament matches
        $tournamentMatchIds = DB::table('matches as m')
            ->join('tournament_types as tt', 'm.tournament_type_id', '=', 'tt.id')
            ->join('teams as t', 'm.home_team_id', '=', 't.id')
            ->join('team_members as tm', 'tm.team_id', '=', 't.id')
            ->where('tm.user_id', $userId)
            ->whereIn('tt.tournament_id', $tournamentIds)
            ->where('m.status', 'completed')
            ->where('m.is_bye', false)
            ->pluck('m.id');

        $validTournamentIds = $tournamentMatchIds->filter(function ($matchId) use ($userId) {
            $match = Matches::with(['homeTeam.members', 'awayTeam.members'])->find($matchId);
            if (!$match) {
                return false;
            }
            $homeHasGuest = $match->homeTeam->members->contains(fn($m) => $m->id === $userId ? false : ($m->is_guest ?? false));
            $awayHasGuest = $match->awayTeam->members->contains(fn($m) => $m->id === $userId ? false : ($m->is_guest ?? false));
            return !($homeHasGuest || $awayHasGuest);
        });

        $matchIds = $matchIds->merge($validTournamentIds->map(fn($id) => ['type' => 'tournament', 'id' => $id]));

        // Mini tournament matches
        $miniMatchIds = DB::table('mini_matches as mm')
            ->join('mini_team_members as mtm', 'mtm.mini_team_id', '=', 'mm.team1_id')
            ->where('mtm.user_id', $userId)
            ->where('mm.status', MiniMatch::STATUS_COMPLETED)
            ->where('mm.is_bye', false)
            ->pluck('mm.id');

        $validMiniIds = $miniMatchIds->filter(function ($matchId) use ($userId) {
            $match = MiniMatch::with(['team1.members', 'team2.members'])->find($matchId);
            if (!$match || !$match->team1) {
                return false;
            }
            $team1HasGuest = $match->team1->members->contains(fn($m) => $m->user_id === $userId ? false : ($m->is_guest ?? false));
            $team2HasGuest = $match->team2 && $match->team2->members->contains(fn($m) => $m->user_id === $userId ? false : ($m->is_guest ?? false));
            return !($team1HasGuest || $team2HasGuest);
        });

        $matchIds = $matchIds->merge($validMiniIds->map(fn($id) => ['type' => 'mini', 'id' => $id]));

        // Quick matches
        $quickMatchIds = DB::table('match_histories as mh')
            ->join('quick_matches as qm', 'mh.quick_match_id', '=', 'qm.id')
            ->where('mh.user_id', $userId)
            ->where('qm.status', 'completed')
            ->pluck('qm.id');

        $validQuickIds = $quickMatchIds->filter(function ($matchId) {
            $match = QuickMatch::find($matchId);
            if (!$match) {
                return false;
            }
            $teamA = is_array($match->team_a ?? null) ? $match->team_a : json_decode($match->team_a ?? '[]', true) ?? [];
            $teamB = is_array($match->team_b ?? null) ? $match->team_b : json_decode($match->team_b ?? '[]', true) ?? [];
            $allUserIds = array_merge($teamA, $teamB);

            return !User::whereIn('id', $allUserIds)->where('is_guest', true)->exists();
        });

        $matchIds = $matchIds->merge($validQuickIds->map(fn($id) => ['type' => 'quick', 'id' => $id]));

        return $matchIds;
    }

    protected function calculateScoreFromMatches(int $userId, int $sportId, $matchIds): float
    {
        if ($matchIds->isEmpty()) {
            return 0;
        }

        $currentScore = 0;
        $userData = DB::table('users')->where('id', $userId)->first();

        foreach ($matchIds as $matchItem) {
            $matchScore = $this->calculateMatchContribution(
                $userId,
                $matchItem['type'],
                $matchItem['id'],
                $currentScore,
                $userData
            );
            $currentScore = $matchScore;
        }

        return round($currentScore, 2);
    }

    protected function calculateMatchContribution(
        int $userId,
        string $matchType,
        int $matchId,
        float $currentScore,
        $userData
    ): float {
        $kFactor = $this->calculateKFactor($userData);
        $actualScore = $this->getMatchActualScore($matchType, $matchId, $userId);
        $expectedScore = $this->getExpectedScore($currentScore);

        $factor = match ($matchType) {
            'tournament' => 0.6,
            default => 0.2,
        };

        return $currentScore + ($factor * $kFactor * ($actualScore - $expectedScore));
    }

    protected function calculateKFactor($userData): float
    {
        if ($userData->is_anchor ?? false) {
            return 0.1;
        }

        $anchored = $userData->total_matches_has_anchor ?? 0;
        if ($anchored <= 10) {
            return 1.0;
        } elseif ($anchored <= 50) {
            return 0.6;
        }

        return 0.3;
    }

    protected function getMatchActualScore(string $matchType, int $matchId, int $userId): float
    {
        return match ($matchType) {
            'tournament' => $this->getTournamentMatchScore($matchId, $userId),
            'mini' => $this->getMiniMatchScore($matchId, $userId),
            'quick' => $this->getQuickMatchScore($matchId, $userId),
        };
    }

    protected function getTournamentMatchScore(int $matchId, int $userId): float
    {
        $match = Matches::with(['results', 'homeTeam.members', 'awayTeam.members'])->find($matchId);
        if (!$match) {
            return 0.5;
        }

        $homeMembers = $match->homeTeam->members->pluck('id')->toArray();
        $isHome = in_array($userId, $homeMembers);
        $userTeamId = $isHome ? $match->home_team_id : $match->away_team_id;
        $opponentTeamId = $isHome ? $match->away_team_id : $match->home_team_id;

        $matchWin = $match->winner_id == $userTeamId ? 1.0 : 0.0;

        $teamScore = $match->results->where('team_id', $userTeamId)->sum('score');
        $opponentScore = $match->results->where('team_id', $opponentTeamId)->sum('score');
        $totalScore = $teamScore + $opponentScore;
        $pointsRatio = $totalScore > 0 ? $teamScore / $totalScore : 0.5;

        return 0.5 * $matchWin + 0.5 * $pointsRatio;
    }

    protected function getMiniMatchScore(int $matchId, int $userId): float
    {
        $match = MiniMatch::with(['results', 'team1.members', 'team2.members'])->find($matchId);
        if (!$match || !$match->team1) {
            return 0.5;
        }

        $team1Members = $match->team1->members->pluck('user_id')->toArray();
        $isTeam1 = in_array($userId, $team1Members);
        $userTeamId = $isTeam1 ? $match->team1_id : $match->team2_id;
        $opponentTeamId = $isTeam1 ? $match->team2_id : $match->team1_id;

        $matchWin = $match->team_win_id == $userTeamId ? 1.0 : 0.0;

        $teamScore = $match->results->where('team_id', $userTeamId)->sum('score');
        $opponentScore = $match->results->where('team_id', $opponentTeamId)->sum('score');
        $totalScore = $teamScore + $opponentScore;
        $pointsRatio = $totalScore > 0 ? $teamScore / $totalScore : 0.5;

        return 0.5 * $matchWin + 0.5 * $pointsRatio;
    }

    protected function getQuickMatchScore(int $matchId, int $userId): float
    {
        $match = QuickMatch::find($matchId);
        if (!$match) {
            return 0.5;
        }

        $teamA = is_array($match->team_a ?? null) ? $match->team_a : json_decode($match->team_a ?? '[]', true) ?? [];
        $isTeamA = in_array($userId, $teamA);

        $score = is_array($match->score ?? null) ? $match->score : json_decode($match->score ?? '{}', true);
        $teamAScore = array_sum($score['team_a'] ?? []);
        $teamBScore = array_sum($score['team_b'] ?? []);
        $totalScore = $teamAScore + $teamBScore;

        $winner = $match->winner ?? null;
        $matchWin = $winner === 'A' ? 1.0 : ($winner === 'B' ? 0.0 : 0.5);
        $pointsRatio = $totalScore > 0 ? ($isTeamA ? $teamAScore : $teamBScore) / $totalScore : 0.5;

        return 0.5 * $matchWin + 0.5 * $pointsRatio;
    }

    protected function getExpectedScore(float $currentScore): float
    {
        return 1 / (1 + pow(10, (1500 - $currentScore) / 400));
    }
}
