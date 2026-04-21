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

    public function search(int $page, int $limit, ?string $status, ?string $keyword): LengthAwarePaginator
    {
        $query = Tournament::query()
            ->select([
                'id',
                'name',
                'status',
                'is_featured',
                'poster_url',
                'start_date',
                'fee',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
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
