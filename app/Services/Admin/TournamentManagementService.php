<?php

namespace App\Services\Admin;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            ->select([
                'id',
                'poster',
                'name',
                'description',
                'status',
                'is_featured',
                'sport_id',
                'start_date',
                'end_date',
                'registration_open_at',
                'registration_closed_at',
                'fee',
                'standard_fee_amount',
                'early_registration_deadline',
                'age_group',
                'gender_policy',
                'participant',
                'max_team',
                'player_per_team',
                'max_player',
                'is_private',
                'auto_approve',
                'competition_location_id',
                'club_id',
                'created_by',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [Tournament::DRAFT, Tournament::OPEN]);
        }

        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    public function approve(Tournament $tournament, User $admin): void
    {
        $oldValues = ['status' => $tournament->status];

        $tournament->update(['status' => 'active']);

        $this->auditLogService->log(
            $admin,
            'approve_tournament',
            Tournament::class,
            $tournament->id,
            $oldValues,
            ['status' => 'active']
        );
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
    }

    public function delete(Tournament $tournament, User $admin): void
    {
        $this->auditLogService->log(
            $admin,
            'delete_tournament',
            Tournament::class,
            $tournament->id,
            $tournament->toArray(),
            null
        );

        $tournament->delete();
    }
}
