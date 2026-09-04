<?php

namespace App\Console\Commands;

use App\Enums\TournamentCleanupType;
use App\Jobs\SendTournamentCleanupNotificationJob;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\Tournament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupEmptyTournaments extends Command
{
    protected $signature = 'tournaments:cleanup-empty
                            {--limit=200 : Override max records to process per run}';

    protected $description = 'Xóa các giải đấu và mini-tournament không có người tham gia hợp lệ khi đã quá thời gian bắt đầu';

    private const CLEANUP_REASON = 'Không có người tham gia hợp lệ khi đã quá thời gian bắt đầu.';

    /**
     * Chunk size khi duyệt DB - giảm từ 100 xuống 50 để giải phóng memory sớm.
     */
    private const CHUNK_SIZE = 50;

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
            // Chỉ lấy các field cần thiết, bỏ ->with('creator') để tránh N+1
            ->select(['id', 'name', 'created_by', 'club_id'])
            ->chunkById(self::CHUNK_SIZE, function ($tournaments) use (&$count, &$remaining) {
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
                // Lưu các giá trị cần thiết trước khi delete (sau khi delete, model vẫn còn
                // trong bộ nhớ nhưng các relation eager-loaded sẽ stale).
                $name = $tournament->name;
                $clubId = $tournament->club_id;
                $creatorId = $tournament->created_by;
                $tournamentId = $tournament->id;

                $tournament->delete();

                $this->line("Deleted tournament #{$tournamentId}");

                Log::info('Tournament cleaned up', [
                    'id' => $tournamentId,
                    'name' => $name,
                    'creator_id' => $creatorId,
                    'reason' => self::CLEANUP_REASON,
                ]);

                // Dispatch job riêng để notify creator - không block transaction.
                // Job sẽ chạy qua queue worker (nếu có) hoặc nằm trong bảng jobs.
                SendTournamentCleanupNotificationJob::dispatch(
                    $creatorId,
                    TournamentCleanupType::Tournament,
                    $name,
                    $clubId,
                    $tournamentId,
                    self::CLEANUP_REASON,
                );

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
            // Chỉ lấy các field cần thiết, bỏ ->with('creator')
            ->select(['id', 'name', 'created_by', 'club_id'])
            ->chunkById(self::CHUNK_SIZE, function ($miniTournaments) use (&$count, &$remaining) {
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
                // Lưu các giá trị cần thiết trước khi delete.
                $name = $miniTournament->name;
                $clubId = $miniTournament->club_id;
                $creatorId = $miniTournament->created_by;
                $miniTournamentId = $miniTournament->id;

                // Cascade các bảng con trước khi soft delete để tránh mồ côi:
                //   - matches có results (mini_match_result) phụ thuộc
                //   - payments có receipt
                //   - staff là BTC của tournament đã xoá
                // Sau khi xoá các bảng con, mới xoá tournament để các FK
                // (nếu có cascade ở DB) an toàn.
                MiniMatch::where('mini_tournament_id', $miniTournamentId)->delete();
                MiniParticipant::where('mini_tournament_id', $miniTournamentId)->delete();
                MiniParticipantPayment::where('mini_tournament_id', $miniTournamentId)->delete();
                MiniTournamentStaff::where('mini_tournament_id', $miniTournamentId)->delete();

                $miniTournament->delete();

                $this->line("Deleted mini-tournament #{$miniTournamentId}");

                Log::info('Mini-tournament cleaned up', [
                    'id' => $miniTournamentId,
                    'name' => $name,
                    'creator_id' => $creatorId,
                    'reason' => self::CLEANUP_REASON,
                ]);

                // Dispatch job riêng để notify creator - không block transaction.
                SendTournamentCleanupNotificationJob::dispatch(
                    $creatorId,
                    TournamentCleanupType::MiniTournament,
                    $name,
                    $clubId,
                    $miniTournamentId,
                    self::CLEANUP_REASON,
                );

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
