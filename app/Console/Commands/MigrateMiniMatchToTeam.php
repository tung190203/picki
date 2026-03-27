<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MiniMatch;
use App\Models\MiniTeam;
use App\Models\MiniTeamMember;

class MigrateMiniMatchToTeam extends Command
{
    protected $signature = 'mini:migrate-match-to-team 
                            {--dry-run}
                            {--limit=}';

    protected $description = 'Migrate mini_matches from participant(user) to team-based';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('🚀 Start migrating mini_matches');
        if ($dryRun) {
            $this->warn('⚠ DRY RUN MODE');
        }

        $query = MiniMatch::query()
            ->whereNotNull('participant1_id')
            ->whereNotNull('participant2_id')
            ->with([
                'participant1:user_id,id',
                'participant2:user_id,id',
            ]);

        if ($limit) {
            $query->limit((int) $limit);
        }

        $matches = $query->get();

        $bar = $this->output->createProgressBar($matches->count());
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($matches as $match) {

                $team1 = $this->getOrCreateSoloTeam(
                    $match->participant1->user_id,
                    $match->mini_tournament_id
                );

                $team2 = $this->getOrCreateSoloTeam(
                    $match->participant2->user_id,
                    $match->mini_tournament_id
                );

                if (!$dryRun) {
                    $match->update([
                        'team1_id' => $team1->id,
                        'team2_id' => $team2->id,
                        'team_win_id' => $this->mapWinner($match, $team1, $team2),
                        'team1_confirm' => $match->participant1_confirm,
                        'team2_confirm' => $match->participant2_confirm,
                    ]);
                    foreach ($match->results as $result) {
                        if ($result->participant_id == $match->participant1_id) {
                            $result->update(['team_id' => $team1->id]);
                        } elseif ($result->participant_id == $match->participant2_id) {
                            $result->update(['team_id' => $team2->id]);
                        }
                    }
                }

                $bar->advance();
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info("\n🧪 Dry run completed – no data written");
            } else {
                DB::commit();
                $this->info("\n✅ Migration completed successfully");
            }

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\n💥 Error: " . $e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * 1 user = 1 solo team
     */
    protected function getOrCreateSoloTeam(int $userId, int $tournamentId): MiniTeam
    {
        $team = MiniTeam::where('mini_tournament_id', $tournamentId)
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if ($team) {
            return $team;
        }

        $team = MiniTeam::create([
            'mini_tournament_id' => $tournamentId,
            'name' => 'Solo-' . $userId,
        ]);

        MiniTeamMember::create([
            'mini_team_id' => $team->id,
            'user_id' => $userId,
            'is_guest' => \App\Models\MiniParticipant::where('mini_tournament_id', $tournamentId)
                ->where('user_id', $userId)
                ->value('is_guest') ?? false,
        ]);

        return $team;
    }

    /**
     * participant winner → team winner
     */
    protected function mapWinner(MiniMatch $match, MiniTeam $team1, MiniTeam $team2): ?int
    {
        if (!$match->participant_win_id) {
            return null;
        }

        if ($match->participant_win_id == $match->participant1_id) {
            return $team1->id;
        }

        if ($match->participant_win_id == $match->participant2_id) {
            return $team2->id;
        }

        return null;
    }
}
