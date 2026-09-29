<?php

namespace App\Console\Commands;

use App\Enums\BadgeType;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Services\TournamentType\TournamentRankService;
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
                            {--force : Re-award badges even if already awarded}
                            {--revoke-stale : Audit users with CHAMPION badge whose team did not actually win a finished tournament. Default dry-run; combine with --apply-revoke to actually delete.}
                            {--apply-revoke : Required together with --revoke-stale to actually delete stale badges. Without this flag, --revoke-stale only lists them.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Award CHAMPION badges to users who won tournaments (based on team_rankings). With --revoke-stale, also remove CHAMPION from users whose team did not actually win any finished tournament.';

    /**
     * Execute the console command.
     */
    public function handle(BadgeService $badgeService, TournamentRankService $rankService): int
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

        // Query closed tournaments whose all matches are completed
        $query = Tournament::query()
            ->where('status', Tournament::CLOSED)
            ->whereDoesntHave('tournamentTypes.matches', function ($q) {
                $q->where('status', '!=', 'completed');
            });

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
        $validWinnerUserIds = [];

        foreach ($tournaments as $tournament) {
            $result = $this->processTournament($tournament, $badgeService, $rankService, $dryRun, $force);

            $totalChampionsAwarded += $result['awarded'];
            $totalAlreadyAwarded += $result['already_awarded'];
            $totalNoRanking += $result['no_ranking'];
            foreach ($result['winner_user_ids'] ?? [] as $uid => $_) {
                $validWinnerUserIds[(int) $uid] = true;
            }
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

        // ===== Optional: revoke stale CHAMPION badges =====
        if ($this->option('revoke-stale')) {
            $this->revokeStaleChampions($validWinnerUserIds, $tournamentId);
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
     * Uses TournamentRankService (same logic as /tournament-types/{id}/rank):
     * - Get tournament_type_id from tournament
     * - Use rankLabelsByTeam() to identify champion (is_champion = true)
     * - Award badges to all members of winning team
     */
    protected function processTournament(
        Tournament $tournament,
        BadgeService $badgeService,
        TournamentRankService $rankService,
        bool $dryRun,
        bool $force
    ): array {
        $result = [
            'awarded' => 0,
            'already_awarded' => 0,
            'no_ranking' => 0,
            'winner_user_ids' => [],
        ];

        // Get tournament type IDs for this tournament
        $tournamentTypeIds = TournamentType::where('tournament_id', $tournament->id)->pluck('id');

        if ($tournamentTypeIds->isEmpty()) {
            $this->line("  ⚠️  Tournament #{$tournament->id} ({$tournament->name}): No tournament types found");
            $result['no_ranking']++;
            return $result;
        }

        // ✅ Dùng TournamentRankService để lấy champion team đúng theo logic bracket.
        // Chỉ nhận champion từ các type có knockout (FORMAT_ELIMINATION / FORMAT_MIXED)
        // và chỉ khi champion_team_id thực sự được resolve từ final match completed.
        // Tránh cấp nhầm cho team top vòng bảng ở type Round Robin.
        $winnerTeams = collect();
        foreach ($tournamentTypeIds as $typeId) {
            $type = TournamentType::find($typeId);
            if (!$type) continue;
            $format = (int) $type->format;
            // Round Robin / pool-only type không có "vô địch" tuyệt đối → bỏ qua
            if (!in_array($format, [TournamentType::FORMAT_ELIMINATION, TournamentType::FORMAT_MIXED], true)) {
                continue;
            }
            $labels = $rankService->rankLabelsByTeam((int) $typeId);
            foreach ($labels as $teamId => $info) {
                if (!empty($info['is_champion'])) {
                    $winnerTeams->push((int) $teamId);
                }
            }
        }

        if ($winnerTeams->isEmpty()) {
            $this->line("  ⚠️  Tournament #{$tournament->id} ({$tournament->name}): No champion team found");
            $result['no_ranking']++;
            return $result;
        }

        $this->newLine();
        $this->line("  🏆 Tournament: {$tournament->name} (ID: {$tournament->id})");

        foreach ($winnerTeams as $teamId) {
            $team = Team::with('members')->find($teamId);

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
                // Skip if member not a valid user (soft-deleted, invalid, etc.)
                if (!$member->id) {
                    $this->line("      ⚠️  Member has no ID - skipping");
                    continue;
                }

                // Double-check user exists (may be soft-deleted or missing)
                $userExists = \App\Models\User::withTrashed()->find($member->id);
                if (!$userExists) {
                    $this->line("      ⚠️  User ID={$member->id} ({$member->full_name}) not found in users table - skipping");
                    continue;
                }

                // Track valid winners so revoke phase can distinguish them from stale badges.
                $result['winner_user_ids'][(int) $member->id] = true;

                $alreadyHasBadge = $badgeService->hasBadge($member->id, BadgeType::CHAMPION);

                if ($alreadyHasBadge && !$force) {
                    $this->line("      ✓ {$member->full_name} (ID: {$member->id}) - Already has CHAMPION badge");
                    $result['already_awarded']++;
                } else {
                    if ($dryRun) {
                        $this->line("      🎯 {$member->full_name} (ID: {$member->id}) - WOULD be awarded CHAMPION badge");
                    } else {
                        try {
                            // created_by user might not exist on this environment → fallback to null
                            $creatorExists = \App\Models\User::withTrashed()->find($tournament->created_by);
                            $createdBy = $creatorExists ? $tournament->created_by : null;
                            $badgeService->grant_champion($member->id, $createdBy);
                            $this->line("      ✅ {$member->full_name} (ID: {$member->id}) - Awarded CHAMPION badge");
                        } catch (\Throwable $e) {
                            $this->line("      ❌ {$member->full_name} (ID: {$member->id}) - ERROR: " . $e->getMessage());
                            continue;
                        }
                    }
                    $result['awarded']++;
                }
            }
        }

        return $result;
    }

    /**
     * Revoke CHAMPION badges from users who do not belong to any real winning team
     * of a finished tournament (status=CLOSED + all matches completed).
     *
     * Default: dry-run. Pass --apply-revoke to actually delete rows.
     * Silent: no notifications sent (admin-driven cleanup).
     *
     * Scope notes:
     * - Users whose CHAMPION comes from a tournament NOT yet finished are NOT touched
     *   here (the tournament may still resolve a champion later).
     * - Only entries whose team participates in one of the finished tournaments AND
     *   whose team is not in that tournament's winner set are removed.
     */
    protected function revokeStaleChampions(array $validWinnerUserIds, ?string $tournamentId): void
    {
        $apply = $this->option('apply-revoke');
        $isDryRun = !$apply;

        $this->newLine();
        $this->info('🧹 Revoke stale CHAMPION badges');
        $this->line('─' . str_repeat('─', 50));
        if ($isDryRun) {
            $this->warn('DRY RUN — pass --apply-revoke to actually delete badges.');
        } else {
            $this->warn('APPLY MODE — stale badges WILL be deleted (silent, no notifications).');
        }

        // All finished tournaments (same predicate used in the award phase)
        $finishedTournaments = Tournament::query()
            ->where('status', Tournament::CLOSED)
            ->whereDoesntHave('tournamentTypes.matches', fn ($q) => $q->where('status', '!=', 'completed'));

        if ($tournamentId) {
            $finishedTournaments->where('id', $tournamentId);
        }

        $finishedTournamentIds = $finishedTournaments->pluck('id')->all();

        if (empty($finishedTournamentIds)) {
            $this->line('  No finished tournaments — nothing to revoke.');
            return;
        }

        // All user_ids currently holding a CHAMPION badge
        $championUserIds = UserBadge::where('badge_type', BadgeType::CHAMPION->value)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (empty($championUserIds)) {
            $this->line('  No users currently hold CHAMPION — nothing to revoke.');
            return;
        }

        // For each candidate user, look up their team memberships in finished tournaments.
        // If ANY of those teams is a real winner of its tournament → keep badge.
        // Otherwise → stale → revoke.
        $staleUserIds = [];
        $keptUserIds = [];

        $rows = DB::table('team_members as tm')
            ->join('teams as t', 't.id', '=', 'tm.team_id')
            ->whereIn('t.tournament_id', $finishedTournamentIds)
            ->whereIn('tm.user_id', $championUserIds)
            ->select('tm.user_id', 't.id as team_id', 't.tournament_id')
            ->get();

        // Group team_ids by tournament_id so we can resolve winners per-tournament.
        $teamsByTournament = [];
        foreach ($rows as $row) {
            $teamsByTournament[(int) $row->tournament_id][(int) $row->team_id] = true;
        }

        // Re-resolve winner team_ids per tournament (same logic as award phase).
        $winnersByTournament = [];
        foreach (array_keys($teamsByTournament) as $tid) {
            $winnersByTournament[$tid] = $this->resolveWinnerTeamIds((int) $tid);
        }

        $userTeamsByTournament = [];
        foreach ($rows as $row) {
            $userTeamsByTournament[(int) $row->user_id][(int) $row->tournament_id][] = (int) $row->team_id;
        }

        foreach ($championUserIds as $uid) {
            $isValid = false;
            foreach ($userTeamsByTournament[$uid] ?? [] as $tid => $teamIds) {
                $winnerTeams = $winnersByTournament[$tid] ?? [];
                foreach ($teamIds as $teamId) {
                    if (in_array($teamId, $winnerTeams, true)) {
                        $isValid = true;
                        break 2;
                    }
                }
            }
            // Also keep users that the current award run itself identified as winners
            // (covers teams whose tournament just finished during this run).
            if (!$isValid && isset($validWinnerUserIds[$uid])) {
                $isValid = true;
            }

            if ($isValid) {
                $keptUserIds[] = $uid;
            } else {
                $staleUserIds[] = $uid;
            }
        }

        $this->line("  Users with CHAMPION checked: " . count($championUserIds));
        $this->line("  Keep (still legitimate winner): " . count($keptUserIds));
        $this->line("  Stale (will be revoked): " . count($staleUserIds));

        if (empty($staleUserIds)) {
            $this->info('  ✓ No stale CHAMPION badges found.');
            return;
        }

        foreach ($staleUserIds as $uid) {
            $user = \App\Models\User::find($uid);
            $name = $user?->full_name ?? "(deleted user #$uid)";
            $this->line("    " . ($isDryRun ? "🟡" : "❌") . " User #{$uid} ({$name}) - " . ($isDryRun ? "WOULD revoke" : "REVOKED"));
        }

        if ($isDryRun) {
            return;
        }

        // Silent delete (no BadgeRevokedNotification, per admin cleanup contract).
        $deleted = UserBadge::where('badge_type', BadgeType::CHAMPION->value)
            ->whereIn('user_id', $staleUserIds)
            ->delete();

        $this->info("  ✅ Revoked {$deleted} stale CHAMPION badge row(s).");
    }

    /**
     * Resolve winner team ids for a single tournament using TournamentRankService.
     * Mirrors the award phase: only formats with a real champion (elimination/mixed).
     *
     * @return array<int, int>
     */
    private function resolveWinnerTeamIds(int $tournamentId): array
    {
        $rankService = app(TournamentRankService::class);
        $typeIds = TournamentType::where('tournament_id', $tournamentId)->pluck('id');
        $winners = [];
        foreach ($typeIds as $typeId) {
            $type = TournamentType::find($typeId);
            if (!$type) continue;
            $format = (int) $type->format;
            if (!in_array($format, [TournamentType::FORMAT_ELIMINATION, TournamentType::FORMAT_MIXED], true)) {
                continue;
            }
            $labels = $rankService->rankLabelsByTeam((int) $typeId);
            foreach ($labels as $teamId => $info) {
                if (!empty($info['is_champion'])) {
                    $winners[] = (int) $teamId;
                }
            }
        }
        return array_values(array_unique($winners));
    }
}
