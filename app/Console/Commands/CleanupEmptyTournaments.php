<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Notifications\TournamentCleanupNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupEmptyTournaments extends Command
{
    protected $signature = 'tournaments:cleanup-empty';

    protected $description = 'Xoa cac giai dau va mini-tournament khong co participant hop le khi da qua thoi gian bat dau';

    private const CLEANUP_REASON = 'Khong co nguoi tham gia hop le ngoai nguoi tao khi da qua thoi gian bat dau.';

    public function handle(): int
    {
        $tournamentCount = $this->cleanupTournaments();
        $miniCount = $this->cleanupMiniTournaments();

        $this->info("Da xoa {$tournamentCount} giai dau va {$miniCount} mini-tournament.");

        return Command::SUCCESS;
    }

    protected function cleanupTournaments(): int
    {
        $count = 0;

        Tournament::query()
            ->whereIn('status', [Tournament::DRAFT, Tournament::OPEN])
            ->whereNotNull('start_date')
            ->where('start_date', '<=', now())
            ->with('creator')
            ->chunkById(100, function ($tournaments) use (&$count) {
                foreach ($tournaments as $tournament) {
                    $this->processTournament($tournament) && $count++;
                }
            });

        return $count;
    }

    protected function processTournament(Tournament $tournament): bool
    {
        try {
            return DB::transaction(function () use ($tournament) {
                $validParticipantCount = $tournament
                    ->participants()
                    ->where('user_id', '!=', $tournament->created_by)
                    ->where('is_guest', false)
                    ->count();

                if ($validParticipantCount > 0) {
                    return false;
                }

                $creator = $tournament->creator;
                $name = $tournament->name;
                $clubId = $tournament->club_id;
                $creatorId = $tournament->created_by;

                $tournament->delete();

                $this->line("Deleted tournament #{$tournament->id}");

                Log::info('Tournament cleaned up', [
                    'id' => $tournament->id,
                    'name' => $name,
                    'creator_id' => $creatorId,
                    'reason' => self::CLEANUP_REASON,
                ]);

                if ($creator) {
                    $creator->notify(new TournamentCleanupNotification(
                        tournamentType: 'giai dau',
                        tournamentName: $name,
                        reason: self::CLEANUP_REASON,
                        clubId: $clubId,
                        tournamentId: $tournament->id,
                    ));
                }

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup tournament', [
                'id' => $tournament->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function cleanupMiniTournaments(): int
    {
        $count = 0;

        MiniTournament::query()
            ->whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
            ->whereNotNull('start_time')
            ->where('start_time', '<=', now())
            ->with('creator')
            ->chunkById(100, function ($miniTournaments) use (&$count) {
                foreach ($miniTournaments as $miniTournament) {
                    $this->processMiniTournament($miniTournament) && $count++;
                }
            });

        return $count;
    }

    protected function processMiniTournament(MiniTournament $miniTournament): bool
    {
        try {
            return DB::transaction(function () use ($miniTournament) {
                $validParticipantCount = $miniTournament
                    ->participants()
                    ->where('user_id', '!=', $miniTournament->created_by)
                    ->where('is_guest', false)
                    ->whereNull('declined_at')
                    ->count();

                if ($validParticipantCount > 0) {
                    return false;
                }

                $creator = $miniTournament->creator;
                $name = $miniTournament->name;
                $clubId = $miniTournament->club_id;
                $creatorId = $miniTournament->created_by;

                $miniTournament->delete();

                $this->line("Deleted mini-tournament #{$miniTournament->id}");

                Log::info('Mini-tournament cleaned up', [
                    'id' => $miniTournament->id,
                    'name' => $name,
                    'creator_id' => $creatorId,
                    'reason' => self::CLEANUP_REASON,
                ]);

                if ($creator) {
                    $creator->notify(new TournamentCleanupNotification(
                        tournamentType: 'mini-tournament',
                        tournamentName: $name,
                        reason: self::CLEANUP_REASON,
                        clubId: $clubId,
                        tournamentId: $miniTournament->id,
                    ));
                }

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup mini-tournament', [
                'id' => $miniTournament->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
