<?php

namespace App\Services\Search;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4: Organizer identity enrichment cho Search match/tournament results.
 *
 * Quy tắc organizer:
 *  - Nếu entity thuộc CLB: organizer = Club::creator user (người tạo CLB)
 *  - Nếu standalone: organizer = entity.created_by user
 *
 * Batch load: 7 query cố định cho bất kỳ số lượng entity. Không N+1.
 */
class SearchOrganizerEnricher
{
    public static function attachOrganizer(Collection|array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $organizerUserIds = [];
        $clubByEntityId = [];

        foreach ($entities as $entity) {
            $club = self::resolveClub($entity);
            if ($club !== null) {
                $clubByEntityId[$entity->id] = $club;
                // Club đã được eager load 'club.creator' → lấy user.id trực tiếp
                $organizerUserIds[] = (int) $club->created_by;
            } else {
                $organizerUserIds[] = (int) ($entity->created_by ?? 0);
            }
        }

        $organizerUserIds = array_values(array_unique(array_filter($organizerUserIds)));
        if (empty($organizerUserIds)) {
            return;
        }

        // 1. Organizer users (id + full_name + avatar_url)
        $usersById = User::whereIn('id', $organizerUserIds)
            ->select(['id', 'full_name', 'avatar_url'])
            ->get()
            ->keyBy('id');

        // 2. vndupr_score: MAX(score_value) GROUP BY user_id
        $vnduprByUser = DB::table('user_sport_scores as uss')
            ->join('user_sport as us', 'us.id', '=', 'uss.user_sport_id')
            ->whereIn('us.user_id', $organizerUserIds)
            ->where('uss.score_type', 'vndupr_score')
            ->whereNotNull('uss.score_value')
            ->groupBy('us.user_id')
            ->selectRaw('us.user_id, MAX(uss.score_value) as max_score')
            ->pluck('max_score', 'us.user_id')
            ->all();

        // 3. organized_count: 4 nguồn aggregate
        $organizedByUser = self::bulkOrganizedCount($organizerUserIds);

        // 4. follower_count: 1 query
        $followersByUser = DB::table('follows')
            ->whereIn('followable_id', $organizerUserIds)
            ->where('followable_type', User::class)
            ->groupBy('followable_id')
            ->selectRaw('followable_id, COUNT(*) as cnt')
            ->pluck('cnt', 'followable_id')
            ->all();

        // 5. Gắn vào từng entity
        foreach ($entities as $entity) {
            $orgUserId = self::resolveOrganizerUserId($entity, $clubByEntityId);
            if (!$orgUserId || !isset($usersById[$orgUserId])) {
                $entity->setAttribute('organizer', null);
                continue;
            }

            $user = $usersById[$orgUserId];
            $club = $clubByEntityId[$entity->id] ?? null;

            // Attach extra stats onto the user model so OrganizerResource can read them
            $user->setAttribute('club', $club ? [
                'id'   => (int) $club->id,
                'name' => $club->name,
            ] : null);
            $user->setAttribute('vndupr_score', isset($vnduprByUser[$orgUserId])
                ? round((float) $vnduprByUser[$orgUserId], 1)
                : null);
            $user->setAttribute('organized_count', (int) ($organizedByUser[$orgUserId] ?? 0));
            $user->setAttribute('follower_count', (int) ($followersByUser[$orgUserId] ?? 0));

            $entity->setAttribute('organizer', $user);
        }
    }

    /**
     * Resolve Club từ entity (đã eager load 'club.creator' qua scopeSearchRelations).
     */
    private static function resolveClub($entity): ?object
    {
        if (!$entity->relationLoaded('club')) {
            return null;
        }
        $club = $entity->getRelation('club');
        if (!$club) {
            return null;
        }
        // Chỉ dùng làm organizer nếu club có created_by
        return $club->created_by ? $club : null;
    }

    private static function resolveOrganizerUserId($entity, array $clubByEntityId): ?int
    {
        if (isset($clubByEntityId[$entity->id])) {
            return (int) $clubByEntityId[$entity->id]->created_by ?: null;
        }
        $createdBy = (int) ($entity->created_by ?? 0);
        return $createdBy ?: null;
    }

    /**
     * Aggregate organized_count từ 4 nguồn:
     *  1. mini_tournaments.created_by
     *  2. mini_tournament_staff (role = 1 = organizer/admin)
     *  3. tournaments.created_by
     *  4. tournament_staff (role = 1 = organizer)
     *
     * Staff count dùng COUNT(DISTINCT id) để tránh đếm trùng khi 1 user có nhiều role
     * trong cùng tournament.
     */
    private static function bulkOrganizedCount(array $userIds): array
    {
        $counts = array_fill_keys($userIds, 0);

        // 1. mini_tournaments.created_by
        $rows = DB::table('mini_tournaments')
            ->whereIn('created_by', $userIds)
            ->groupBy('created_by')
            ->selectRaw('created_by, COUNT(*) as cnt')
            ->pluck('cnt', 'created_by')
            ->all();
        foreach ($rows as $uid => $cnt) {
            $counts[(int) $uid] = ($counts[(int) $uid] ?? 0) + (int) $cnt;
        }

        // 3. tournaments.created_by
        $rows = DB::table('tournaments')
            ->whereIn('created_by', $userIds)
            ->groupBy('created_by')
            ->selectRaw('created_by, COUNT(*) as cnt')
            ->pluck('cnt', 'created_by')
            ->all();
        foreach ($rows as $uid => $cnt) {
            $counts[(int) $uid] = ($counts[(int) $uid] ?? 0) + (int) $cnt;
        }

        // 2. mini_tournament_staff (role = 1 = ROLE_ORGANIZER/ROLE_ADMIN)
        $rows = DB::table('mini_tournament_staff')
            ->whereIn('user_id', $userIds)
            ->where('role', 1)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT mini_tournament_id) as cnt')
            ->pluck('cnt', 'user_id')
            ->all();
        foreach ($rows as $uid => $cnt) {
            $counts[(int) $uid] = ($counts[(int) $uid] ?? 0) + (int) $cnt;
        }

        // 4. tournament_staff (role = 1 = ROLE_ORGANIZER)
        $rows = DB::table('tournament_staff')
            ->whereIn('user_id', $userIds)
            ->where('role', 1)
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT tournament_id) as cnt')
            ->pluck('cnt', 'user_id')
            ->all();
        foreach ($rows as $uid => $cnt) {
            $counts[(int) $uid] = ($counts[(int) $uid] ?? 0) + (int) $cnt;
        }

        return $counts;
    }
}