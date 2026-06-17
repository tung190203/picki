<?php

namespace App\Services\Admin;

use App\Http\Resources\Admin\AdminCompetitionLocationResource;
use App\Models\CompetitionLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminCompetitionLocationManagementService
{
    public function search(
        int $page,
        int $limit,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = CompetitionLocation::query()
            ->withAdminRelations()
            ->filterForAdmin($filters)
            ->sortForAdmin($sortBy, $sortDir);

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return $paginated->setCollection(
            collect(AdminCompetitionLocationResource::collection($paginated->getCollection())->resolve())
        );
    }

    public function getOne(int $id): array
    {
        $location = CompetitionLocation::query()
            ->withAdminRelations()
            ->find($id);

        if (!$location) {
            throw new ModelNotFoundException("Competition location with ID {$id} not found.");
        }

        return (new AdminCompetitionLocationResource($location))->resolve();
    }

    public function updateStatus(CompetitionLocation $location, string $status): CompetitionLocation
    {
        if (!in_array($status, ['active', 'banned'], true)) {
            throw new \InvalidArgumentException("Invalid status value: {$status}. Allowed: active, banned.");
        }

        $location->status = $status;
        $location->save();

        return $location;
    }

    public function toggleBan(CompetitionLocation $location, bool $isBanned): CompetitionLocation
    {
        $location->is_banned = $isBanned;
        $location->status = $isBanned ? 'banned' : 'active';
        $location->save();

        return $location;
    }
}
