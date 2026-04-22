<?php

namespace App\Services\Admin;

use App\Models\MiniTournament;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MiniTournamentManagementService
{
    private const PLAYERS_COUNT_SQL = '(SELECT COUNT(*) FROM mini_participants WHERE mini_participants.mini_tournament_id = mini_tournaments.id) as players_count';
    private const HAS_DISPUTE_SQL = "(SELECT COUNT(*) FROM disputes WHERE disputes.match_id IN (SELECT id FROM mini_matches WHERE mini_matches.mini_tournament_id = mini_tournaments.id) AND disputes.status = 'open') as has_dispute";

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function search(int $page, int $limit, mixed $status, ?string $keyword): LengthAwarePaginator
    {
        $query = MiniTournament::with([
            'competitionLocation',
            'sport',
            'creator',
            'club',
            'participants.user',
            'miniTournamentStaffs.user',
        ])
            ->whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
            ->select([
                'mini_tournaments.id',
                'mini_tournaments.poster',
                'mini_tournaments.name',
                'mini_tournaments.description',
                'mini_tournaments.status',
                'mini_tournaments.start_time',
                'mini_tournaments.competition_location_id',
                'mini_tournaments.created_by',
                'mini_tournaments.club_id',
                'mini_tournaments.sport_id',
                'mini_tournaments.play_mode',
                'mini_tournaments.format',
                'mini_tournaments.gender',
                'mini_tournaments.has_fee',
                'mini_tournaments.fee_amount',
                'mini_tournaments.auto_split_fee',
                'mini_tournaments.max_players',
                'mini_tournaments.is_private',
                'mini_tournaments.created_at',
                DB::raw(self::PLAYERS_COUNT_SQL),
                DB::raw(self::HAS_DISPUTE_SQL),
            ])
            ->orderBy('created_at', 'desc');

        if ($status !== null && $status !== '') {
            $query->where('mini_tournaments.status', $status);
        }

        if ($keyword) {
            $query->where('mini_tournaments.name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function approve(MiniTournament $miniTournament, User $admin): void
    {
        $oldValues = ['status' => $miniTournament->status];

        $miniTournament->update(['status' => MiniTournament::STATUS_OPEN]);

        $this->auditLogService->log(
            $admin,
            'approve_mini_tournament',
            MiniTournament::class,
            $miniTournament->id,
            $oldValues,
            ['status' => MiniTournament::STATUS_OPEN]
        );
    }

    public function feature(MiniTournament $miniTournament, User $admin): void
    {
        $this->auditLogService->log(
            $admin,
            'feature_mini_tournament',
            MiniTournament::class,
            $miniTournament->id,
            [],
            ['action' => 'feature']
        );
    }

    public function unfeature(MiniTournament $miniTournament, User $admin): void
    {
        $this->auditLogService->log(
            $admin,
            'unfeature_mini_tournament',
            MiniTournament::class,
            $miniTournament->id,
            [],
            ['action' => 'unfeature']
        );
    }

    public function delete(MiniTournament $miniTournament, User $admin): void
    {
        $this->auditLogService->log(
            $admin,
            'delete_mini_tournament',
            MiniTournament::class,
            $miniTournament->id,
            $miniTournament->toArray(),
            null
        );

        $miniTournament->delete();
    }
}
