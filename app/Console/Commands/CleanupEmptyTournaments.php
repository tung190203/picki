<?php

namespace App\Console\Commands;

use App\Enums\TournamentCleanupType;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\Tournament;
use App\Notifications\TournamentCleanupNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupEmptyTournaments extends Command
{
    protected $signature = 'tournaments:cleanup-empty
                            {--limit=200 : Override max records to process per run}';

    protected $description = 'Xóa các giải đấu và mini-tournament không có người tham gia hợp lệ khi đã quá thời gian bắt đầu';

    private const CLEANUP_REASON = 'Không có người tham gia hợp lệ khi đã quá thời gian bắt đầu.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $remaining = $limit;

        $tournamentCount = $this->cleanupTournaments($remaining);
        $remaining -= $tournamentCount;
        if ($remaining <= 0) {
            return $this->finishRun($limit, $tournamentCount, 0);
        }

        $miniCount = $this->cleanupMiniTournaments($remaining);
        return $this->finishRun($limit, $tournamentCount, $miniCount);
    }

    private function finishRun(int $limit, int $tournaments, int $mini): int
    {
        $this->info("Đã xóa {$tournaments} giải đấu và {$mini} mini-tournament (giới hạn {$limit}/run).");
        return Command::SUCCESS;
    }

    protected function cleanupTournaments(int $remaining): int
    {
        $count = 0;

        Tournament::query()
            ->whereIn('status', [
                Tournament::DRAFT,
                Tournament::OPEN,
                Tournament::CLOSED,
                Tournament::CANCELLED,
            ])
            ->whereNotNull('start_date')
            ->where('start_date', '<=', now()->subHours(24))
            ->whereNull('deleted_at')
            // Lọc ngay tầng DB: chỉ lấy các giải KHÔNG có participants hợp lệ
            // (user_id != created_by). Tránh N+1 query trong transaction.
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('participants')
                    ->whereColumn('participants.tournament_id', 'tournaments.id')
                    ->whereColumn('participants.user_id', '!=', 'tournaments.created_by');
            })
            ->with('creator')
            ->chunkById(100, function ($tournaments) use (&$count, &$remaining) {
                foreach ($tournaments as $tournament) {
                    if ($remaining <= 0) {
                        return false; // dừng chunk
                    }
                    $this->processTournament($tournament) && $count++;
                    $remaining--;
                }
                return null; // tiếp tục
            });

        return $count;
    }

    protected function processTournament(Tournament $tournament): bool
    {
        try {
            return DB::transaction(function () use ($tournament) {
                // Filter đã chạy ở tầng DB (whereNotExists trong cleanupTournaments),
                // nên trong transaction không cần count lại participants nữa.
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
                    $creator->notify(
                        (new TournamentCleanupNotification(
                            tournamentType: TournamentCleanupType::Tournament,
                            tournamentName: $name,
                            reason: self::CLEANUP_REASON,
                            clubId: $clubId,
                            tournamentId: $tournament->id,
                        ))->afterCommit()
                    );
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

    protected function cleanupMiniTournaments(int $remaining): int
    {
        $count = 0;

        MiniTournament::query()
            ->whereIn('status', [
                MiniTournament::STATUS_DRAFT,
                MiniTournament::STATUS_OPEN,
                MiniTournament::STATUS_CLOSED,
                MiniTournament::STATUS_CANCELLED,
            ])
            ->whereNotNull('start_time')
            ->where('start_time', '<=', now()->subHours(24))
            ->whereNull('deleted_at')
            // Lọc ngay tầng DB: chỉ lấy các mini-tournament KHÔNG có participants hợp lệ
            // (user_id != created_by và chưa declined). Tránh N+1 query trong transaction.
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('mini_participants')
                    ->whereColumn('mini_participants.mini_tournament_id', 'mini_tournaments.id')
                    ->whereColumn('mini_participants.user_id', '!=', 'mini_tournaments.created_by')
                    ->whereNull('mini_participants.declined_at');
            })
            ->with('creator')
            ->chunkById(100, function ($miniTournaments) use (&$count, &$remaining) {
                foreach ($miniTournaments as $miniTournament) {
                    if ($remaining <= 0) {
                        return false;
                    }
                    $this->processMiniTournament($miniTournament) && $count++;
                    $remaining--;
                }
                return null;
            });

        return $count;
    }

    protected function processMiniTournament(MiniTournament $miniTournament): bool
    {
        try {
            return DB::transaction(function () use ($miniTournament) {
                // Filter đã chạy ở tầng DB (whereNotExists trong cleanupMiniTournaments),
                // nên trong transaction không cần count lại participants nữa.
                $creator = $miniTournament->creator;
                $name = $miniTournament->name;
                $clubId = $miniTournament->club_id;
                $creatorId = $miniTournament->created_by;

                // Cascade các bảng con trước khi soft delete để tránh mồ côi:
                //   - matches có results (mini_match_result) phụ thuộc
                //   - payments có receipt
                //   - staff là BTC của tournament đã xoá
                // Sau khi xoá các bảng con, mới xoá tournament để các FK
                // (nếu có cascade ở DB) an toàn.
                MiniMatch::where('mini_tournament_id', $miniTournament->id)->delete();
                MiniParticipant::where('mini_tournament_id', $miniTournament->id)->delete();
                MiniParticipantPayment::where('mini_tournament_id', $miniTournament->id)->delete();
                MiniTournamentStaff::where('mini_tournament_id', $miniTournament->id)->delete();

                $miniTournament->delete();

                $this->line("Deleted mini-tournament #{$miniTournament->id}");

                Log::info('Mini-tournament cleaned up', [
                    'id' => $miniTournament->id,
                    'name' => $name,
                    'creator_id' => $creatorId,
                    'reason' => self::CLEANUP_REASON,
                ]);

                if ($creator) {
                    $creator->notify(
                        (new TournamentCleanupNotification(
                            tournamentType: TournamentCleanupType::MiniTournament,
                            tournamentName: $name,
                            reason: self::CLEANUP_REASON,
                            clubId: $clubId,
                            tournamentId: $miniTournament->id,
                        ))->afterCommit()
                    );
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
