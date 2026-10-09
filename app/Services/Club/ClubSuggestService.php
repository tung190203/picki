<?php

namespace App\Services\Club;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Models\Club\Club;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3: Club suggestion với 4 nhóm (friend_in_club, following,
 * suit_level, nearby). Tối đa 10 clubs/nhóm, tổng cộng max 40.
 *
 * Reuse Phase 1 ClubSearchEnricher cho toàn bộ card fields.
 * Không cache.
 */
class ClubSuggestService
{
    private const GROUP_LIMIT = 10;
    private const TOTAL_LIMIT = 40;
    private const SUIT_LEVEL_TOLERANCE = 0.3;
    private const NEARBY_RADIUS_KM = 10.0;

    public function suggest(int $userId, ?float $lat, ?float $lng): Collection
    {
        // Phase 1 enricher yêu cầu Eloquent Collection để set dirty attributes
        $result = new EloquentCollection();
        $seen = [];

        // Group 1: friend_in_club
        foreach ($this->queryFriendInClub($userId) as $club) {
            if (!isset($seen[$club->id])) {
                $seen[$club->id] = true;
                $result->push($club);
            }
        }

        // Group 2: following
        foreach ($this->queryFollowing($userId) as $club) {
            if (!isset($seen[$club->id])) {
                $seen[$club->id] = true;
                $result->push($club);
            }
        }

        // Group 3: suit_level
        $userScore = $this->getUserVnduprScore($userId);
        if ($userScore !== null) {
            foreach ($this->querySuitLevel($userScore) as $club) {
                if (!isset($seen[$club->id])) {
                    $seen[$club->id] = true;
                    $result->push($club);
                }
            }
        }

        // Group 4: nearby (chỉ khi có lat/lng)
        if ($lat !== null && $lng !== null) {
            foreach ($this->queryNearby($lat, $lng, self::NEARBY_RADIUS_KM) as $club) {
                if (!isset($seen[$club->id])) {
                    $seen[$club->id] = true;
                    $result->push($club);
                }
            }
        }

        // Batch enrich (Phase 1 ClubSearchEnricher)
        if ($result->isNotEmpty()) {
            ClubSearchEnricher::enrich($result, $userId);
        }

        // Attach distance cho mọi club (nearby đã có sẵn, các nhóm khác cần tính)
        if ($lat !== null && $lng !== null && $result->isNotEmpty()) {
            $this->attachDistance($result, $lat, $lng);
        }

        return $result->take(self::TOTAL_LIMIT)->values();
    }

    /**
     * Gắn distance từ (lat, lng) cho mọi club chưa có attribute này.
     * Bỏ qua nếu club không có tọa độ.
     */
    private function attachDistance(EloquentCollection $clubs, float $lat, float $lng): void
    {
        $haversine = "(6371 * acos(cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude))))";

        $ids = $clubs->filter(fn($c) => $c->latitude !== null && $c->longitude !== null && !isset($c->distance))->pluck('id')->all();
        if (empty($ids)) {
            return;
        }

        $rows = DB::table('clubs')
            ->whereIn('id', $ids)
            ->select('id')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->get()
            ->keyBy('id');

        foreach ($clubs as $club) {
            if (isset($club->distance) || !isset($rows[$club->id])) {
                continue;
            }
            $club->setAttribute('distance', (float) $rows[$club->id]->distance);
        }
    }

    /**
     * friend_in_club → fallback following nếu friend empty.
     */
    /**
     * CLB có bạn bè của user đang joined. Sort:
     *   friends_in_club DESC, is_verified DESC, c.id DESC.
     */
    private function queryFriendInClub(int $userId): Collection
    {
        $friendIds = User::find($userId)?->friends()->pluck('id')->all() ?? [];
        if (empty($friendIds)) {
            return collect();
        }

        // Step 1: ranking query - chỉ aggregate để tránh only_full_group_by
        $ranked = DB::table('club_members as cm')
            ->whereIn('cm.user_id', $friendIds)
            ->where('cm.membership_status', ClubMembershipStatus::Joined->value)
            ->where('cm.status', ClubMemberStatus::Active->value)
            ->groupBy('cm.club_id')
            ->select([
                'cm.club_id',
                DB::raw('COUNT(cm.user_id) as friends_in_club_count'),
            ])
            ->orderByDesc('friends_in_club_count')
            ->orderByDesc('cm.club_id')
            ->limit(self::GROUP_LIMIT)
            ->get();

        $ids = $ranked->pluck('club_id')->all();
        if (empty($ids)) {
            return collect();
        }

        // Step 2: load full Club models + filter status/public
        $clubs = Club::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', ClubStatus::Suspended->value)
            ->where('is_public', true)
            ->get()
            ->keyBy('id');

        // Trả về theo thứ tự ranking, gắn friends_in_club_count + category
        $countsById = $ranked->pluck('friends_in_club_count', 'club_id');
        $result = [];
        foreach ($ids as $id) {
            if (isset($clubs[$id])) {
                $c = $clubs[$id];
                $c->setAttribute('friends_in_club_count', (int) $countsById[$id]);
                $c->setAttribute('category', 'friend_in_club');
                $c->setAttribute('category_text', 'CLB có bạn bè của bạn');
                $result[] = $c;
            }
        }
        return new EloquentCollection($result);
    }

    /**
     * CLB mà user đang follow. Sort: follows.created_at DESC.
     */
    private function queryFollowing(int $userId): Collection
    {
        $ids = DB::table('follows as f')
            ->where('f.user_id', $userId)
            ->where('f.followable_type', Club::class)
            ->orderByDesc('f.created_at')
            ->limit(self::GROUP_LIMIT)
            ->pluck('f.followable_id')
            ->all();

        if (empty($ids)) {
            return collect();
        }

        // Preserve follow-created_at order via FIELD() — whereIn() does not guarantee order
        $clubs = Club::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', ClubStatus::Suspended->value)
            ->where('is_public', true)
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
            ->get();

        foreach ($clubs as $club) {
            $club->setAttribute('category', 'following');
            $club->setAttribute('category_text', 'CLB bạn đang theo dõi');
        }

        return $clubs;
    }

    /**
     * CLB có score range phù hợp với user score (tolerance ±0.3).
     * Sort: abs(midpoint - user_score) ASC.
     */
    private function querySuitLevel(float $userScore): Collection
    {
        $min = $userScore - self::SUIT_LEVEL_TOLERANCE;
        $max = $userScore + self::SUIT_LEVEL_TOLERANCE;

        // Step 1: ranking + aggregate (avoid only_full_group_by)
        $ranked = DB::table('club_members as cm')
            ->join('user_sport as us', 'us.user_id', '=', 'cm.user_id')
            ->join('user_sport_scores as uss', 'uss.user_sport_id', '=', 'us.id')
            ->where('cm.membership_status', ClubMembershipStatus::Joined->value)
            ->where('cm.status', ClubMemberStatus::Active->value)
            ->where('uss.score_type', 'vndupr_score')
            ->whereNotNull('uss.score_value')
            ->groupBy('cm.club_id')
            ->havingRaw('MIN(uss.score_value) >= ?', [$min])
            ->havingRaw('MAX(uss.score_value) <= ?', [$max])
            ->select([
                'cm.club_id',
                DB::raw('MIN(uss.score_value) as club_min'),
                DB::raw('MAX(uss.score_value) as club_max'),
            ])
            ->limit(self::GROUP_LIMIT * 3)
            ->get();

        $ids = $ranked->pluck('club_id')->all();
        if (empty($ids)) {
            return collect();
        }

        // Step 2: load Clubs (filter status/public)
        $clubs = Club::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', ClubStatus::Suspended->value)
            ->where('is_public', true)
            ->get()
            ->keyBy('id');

        // Step 3: hydrate + sort + cap
        $byId = $ranked->keyBy('club_id');
        $hydrated = new EloquentCollection();
        foreach ($ids as $id) {
            if (!isset($clubs[$id])) {
                continue;
            }
            $row = $byId[$id];
            $clubMin = (float) $row->club_min;
            $clubMax = (float) $row->club_max;
            $club = $clubs[$id];
            $club->setAttribute('skill_level', ['min' => $clubMin, 'max' => $clubMax]);
            $club->setAttribute('score_match_score', abs((($clubMin + $clubMax) / 2) - $userScore));
            $club->setAttribute('user_vndupr_score', $userScore);
            $club->setAttribute('category', 'suit_level');
            $club->setAttribute('category_text', 'CLB hợp trình độ của bạn');
            $hydrated->push($club);
        }

        return $hydrated
            ->sortBy('score_match_score')
            ->take(self::GROUP_LIMIT)
            ->values();
    }

    /**
     * CLB trong bán kính radiusKm từ (lat, lng). Sort: distance ASC.
     */
    private function queryNearby(float $lat, float $lng, float $radiusKm): Collection
    {
        $kmToDegLat = $radiusKm / 111.0;
        $kmToDegLng = $radiusKm / (111.0 * cos(deg2rad($lat)));
        $minLat = $lat - $kmToDegLat - 0.5;
        $maxLat = $lat + $kmToDegLat + 0.5;
        $minLng = $lng - $kmToDegLng - 0.5;
        $maxLng = $lng + $kmToDegLng + 0.5;

        $haversine = "(6371 * acos(cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude))))";

        $rows = DB::table('clubs')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->where('status', '!=', ClubStatus::Suspended->value)
            ->where('is_public', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance')
            ->limit(self::GROUP_LIMIT)
            ->get();

        $ids = $rows->pluck('id')->all();
        if (empty($ids)) {
            return collect();
        }

        // Re-load qua Eloquent để có model đầy đủ + giữ distance
        $clubs = Club::query()->whereIn('id', $ids)->get()->keyBy('id');
        $distanceById = $rows->pluck('distance', 'id');

        $result = new EloquentCollection();
        foreach ($ids as $id) {
            if (!isset($clubs[$id])) {
                continue;
            }
            $club = $clubs[$id];
            $club->setAttribute('distance', (float) $distanceById[$id]);
            $club->setAttribute('category', 'nearby');
            $club->setAttribute('category_text', 'CLB gần bạn');
            $result->push($club);
        }
        return $result->values();
    }

    private function getUserVnduprScore(int $userId): ?float
    {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }
        $score = $user->vnduprScoresBySport(Sport::PICKLEBALL_ID)->max('score_value');
        return $score !== null ? (float) $score : null;
    }
}