<?php

namespace App\Services\Admin;

use App\Enums\ClubStatus;
use App\Http\Resources\Admin\AdminClubResource;
use App\Models\Club\Club;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminClubManagementService
{
    public function search(
        int $page,
        int $limit,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = Club::query()
            ->with([
                'adminMember.user',
                'activeMembers',
                'tournaments',
                'miniTournaments',
                'notifications',
            ])
            ->filterForAdmin($filters)
            ->sortForAdmin($sortBy, $sortDir);

        $paginated = $query->paginate($limit, ['*'], 'page', $page);

        return $paginated->setCollection(
            collect(AdminClubResource::collection($paginated->getCollection())->resolve())
        );
    }

    public function getOne(int $id): array
    {
        $club = Club::query()
            ->with([
                'adminMember.user',
                'activeMembers',
                'tournaments',
                'miniTournaments',
                'notifications',
            ])
            ->find($id);

        if (!$club) {
            throw new ModelNotFoundException("Club with ID {$id} not found.");
        }

        return (new AdminClubResource($club))->resolve();
    }

    public function updateStatus(Club $club, string $status): Club
    {
        $mappedStatus = match ($status) {
            'banned' => ClubStatus::Suspended,
            'active' => ClubStatus::Active,
            default => throw new \InvalidArgumentException("Invalid status value: {$status}. Allowed: active, banned."),
        };

        $club->status = $mappedStatus;
        $club->save();

        return $club;
    }

    public function toggleBan(Club $club, bool $isBanned): Club
    {
        $club->is_banned = $isBanned;

        if ($isBanned) {
            $club->status = ClubStatus::Suspended;
        } else {
            $club->status = ClubStatus::Active;
        }

        $club->save();

        return $club;
    }
}
