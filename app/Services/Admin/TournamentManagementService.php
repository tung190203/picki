<?php

namespace App\Services\Admin;

use App\Events\SuperAdmin\DashboardStatUpdated;
use App\Events\SuperAdmin\TournamentDeleted;
use App\Events\SuperAdmin\TournamentUpdated;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TournamentManagementService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function search(int $page, int $limit, mixed $status, ?string $keyword): LengthAwarePaginator
    {
        $query = Tournament::with([
            'competitionLocation',
            'sport',
            'createdBy',
            'club',
            'participants.user',
            'tournamentStaffs.user',
        ])
            ->whereIn('status', [Tournament::DRAFT, Tournament::OPEN])
            ->select([
                'tournaments.id',
                'tournaments.poster',
                'tournaments.name',
                'tournaments.description',
                'tournaments.status',
                'tournaments.is_featured',
                'tournaments.sport_id',
                'tournaments.start_date',
                'tournaments.end_date',
                'tournaments.registration_open_at',
                'tournaments.registration_closed_at',
                'tournaments.fee',
                'tournaments.standard_fee_amount',
                'tournaments.early_registration_deadline',
                'tournaments.age_group',
                'tournaments.gender_policy',
                'tournaments.participant',
                'tournaments.max_team',
                'tournaments.player_per_team',
                'tournaments.max_player',
                'tournaments.is_private',
                'tournaments.auto_approve',
                'tournaments.competition_location_id',
                'tournaments.club_id',
                'tournaments.created_by',
                'tournaments.created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($status !== null && $status !== '') {
            $query->where('tournaments.status', $status);
        }

        if ($keyword) {
            $query->where('tournaments.name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function approve(Tournament $tournament, User $admin): void
    {
        $oldValues = ['status' => $tournament->status];

        $tournament->update(['status' => Tournament::OPEN]);

        $this->auditLogService->log(
            $admin,
            'approve_tournament',
            Tournament::class,
            $tournament->id,
            $oldValues,
            ['status' => Tournament::OPEN]
        );

        $tournament->refresh();
        $tournament->load(['sport', 'club', 'createdBy', 'participants']);
        TournamentUpdated::dispatch($tournament, $oldValues);
    }

    public function feature(Tournament $tournament, User $admin): void
    {
        $oldValues = ['is_featured' => $tournament->is_featured];

        $tournament->update(['is_featured' => true]);

        $this->auditLogService->log(
            $admin,
            'feature_tournament',
            Tournament::class,
            $tournament->id,
            $oldValues,
            ['is_featured' => true]
        );

        $tournament->refresh();
        $tournament->load(['sport', 'club', 'createdBy', 'participants']);
        TournamentUpdated::dispatch($tournament, $oldValues);
    }

    public function unfeature(Tournament $tournament, User $admin): void
    {
        $oldValues = ['is_featured' => $tournament->is_featured];

        $tournament->update(['is_featured' => false]);

        $this->auditLogService->log(
            $admin,
            'unfeature_tournament',
            Tournament::class,
            $tournament->id,
            $oldValues,
            ['is_featured' => false]
        );

        $tournament->refresh();
        $tournament->load(['sport', 'club', 'createdBy', 'participants']);
        TournamentUpdated::dispatch($tournament, $oldValues);
    }

    public function delete(Tournament $tournament, User $admin): void
    {
        $tournamentId = $tournament->id;
        $tournamentName = $tournament->name;

        $this->auditLogService->log(
            $admin,
            'delete_tournament',
            Tournament::class,
            $tournament->id,
            $tournament->toArray(),
            null
        );

        $tournament->delete();

        TournamentDeleted::dispatch($tournamentId, $tournamentName);
        DashboardStatUpdated::dispatch('tournaments_this_month', 1, 'decremented');
        DashboardStatUpdated::dispatch('active_tournaments', 1, 'decremented');
    }
}
