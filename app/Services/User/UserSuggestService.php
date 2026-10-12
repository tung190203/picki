<?php

namespace App\Services\User;

use App\Models\Club\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * User suggestion service - tương tự ClubSuggestService.
 * Trả về 3 nhóm user (tối đa 15 users/nhóm):
 *   1. played_tournament - Hay chơi cùng kèo giải
 *   2. same_club - Cùng CLB
 *   3. nearby - Ở gần bạn
 */
class UserSuggestService
{
    private const GROUP_LIMIT = 15;
    private const NEARBY_RADIUS_KM = 10.0;

    /**
     * @param int $userId User hiện tại đang login
     * @param float|null $lat Vĩ độ của user (nếu có)
     * @param float|null $lng Kinh độ của user (nếu có)
     * @return Collection<EloquentCollection> Collection chứa 3 EloquentCollection (played_tournament, same_club, nearby)
     */
    public function suggest(int $userId, ?float $lat, ?float $lng): array
    {
        $result = [
            'played_tournament' => new EloquentCollection(),
            'same_club' => new EloquentCollection(),
            'nearby' => new EloquentCollection(),
        ];
        $seen = [];

        // Group 1: played_tournament (users đã chơi cùng nhiều trận nhất)
        $playedWith = $this->queryPlayedTogether($userId);
        foreach ($playedWith as $user) {
            if (!isset($seen[$user->id])) {
                $seen[$user->id] = true;
                $result['played_tournament']->push($user);
            }
        }

        // Group 2: same_club (cùng CLB với user hiện tại)
        $sameClub = $this->querySameClub($userId);
        foreach ($sameClub as $user) {
            if (!isset($seen[$user->id])) {
                $seen[$user->id] = true;
                $result['same_club']->push($user);
            }
        }

        // Group 3: nearby (ở gần bạn - chỉ khi có lat/lng)
        if ($lat !== null && $lng !== null) {
            $nearby = $this->queryNearby($userId, $lat, $lng, self::NEARBY_RADIUS_KM);
            foreach ($nearby as $user) {
                if (!isset($seen[$user->id])) {
                    $seen[$user->id] = true;
                    $result['nearby']->push($user);
                }
            }
        }

        // Compute distance cho tất cả users từ (lat, lng) anchor
        if ($lat !== null && $lng !== null) {
            $this->computeDistances($result, $lat, $lng);
        }

        // Batch attach relations cho tất cả users
        $allUsers = $result['played_tournament']
            ->merge($result['same_club'])
            ->merge($result['nearby']);

        if ($allUsers->isNotEmpty()) {
            $this->attachRelations($allUsers, $userId);
        }

        return $result;
    }

    /**
     * Trả về danh sách user_id đã từng chơi cùng current user (top GROUP_LIMIT theo tổng matches).
     * Public để reuse trong scope/filter (sub_tab=played_together).
     * Không load full User models — chỉ trả array id.
     */
    public function getPlayedTogetherUserIds(int $userId): array
    {
        return $this->queryPlayedTogether($userId)
            ->pluck('id')
            ->all();
    }

    /**
     * Group 1: Users đã chơi cùng nhiều trận nhất với current user.
     * Đếm qua: mini_matches + tournament_matches + quick_matches
     */
    private function queryPlayedTogether(int $userId): Collection
    {
        // Step 1: Đếm số trận đã chơi cùng với mỗi user khác
        // Lấy hết (không limit) — top N sẽ sort ở cuối sau khi merge cả 4 nguồn.
        $playedWithCounts = DB::table('match_histories as mh')
            ->join('match_histories as mh_other', 'mh_other.quick_match_id', '=', 'mh.quick_match_id')
            ->where('mh.user_id', $userId)
            ->where('mh_other.user_id', '!=', $userId)
            ->groupBy('mh_other.user_id')
            ->select([
                'mh_other.user_id',
                DB::raw('COUNT(DISTINCT mh.quick_match_id) as matches_together'),
            ])
            ->get()
            ->keyBy('user_id');

        // Mini tournament matches (team-based) - join via mini_teams.mini_tournament_id
        // mini_team_members only has mini_team_id, not mini_tournament_id
        $miniMatches = DB::table('mini_team_members as mtm1')
            ->join('mini_teams as mt1', 'mtm1.mini_team_id', '=', 'mt1.id')
            ->join('mini_teams as mt2', 'mt1.mini_tournament_id', '=', 'mt2.mini_tournament_id')
            ->join('mini_team_members as mtm2', 'mt2.id', '=', 'mtm2.mini_team_id')
            ->where('mtm1.user_id', $userId)
            ->where('mtm2.user_id', '!=', $userId)
            ->whereColumn('mtm1.mini_team_id', '!=', 'mtm2.mini_team_id')
            ->groupBy('mtm2.user_id')
            ->select([
                'mtm2.user_id',
                DB::raw('COUNT(DISTINCT mt1.mini_tournament_id) as mini_tournaments_together'),
            ])
            ->get()
            ->keyBy('user_id');

        // Mini tournament matches (participant-based - solo players)
        $miniParticipants = DB::table('mini_participants as mp1')
            ->join('mini_matches as mm', function ($join) {
                $join->on('mp1.mini_tournament_id', '=', 'mm.mini_tournament_id')
                    ->on(function ($q) {
                        $q->whereColumn('mp1.id', '=', 'mm.participant1_id')
                            ->orWhereColumn('mp1.id', '=', 'mm.participant2_id');
                    });
            })
            ->join('mini_participants as mp2', function ($join) use ($userId) {
                $join->on('mp2.mini_tournament_id', '=', 'mp1.mini_tournament_id')
                    ->where('mp2.user_id', '!=', $userId);
            })
            ->where('mp1.user_id', $userId)
            ->groupBy('mp2.user_id')
            ->select([
                'mp2.user_id',
                DB::raw('COUNT(DISTINCT mp1.mini_tournament_id) as mini_tournaments_participant'),
            ])
            ->get()
            ->keyBy('user_id');

        // Tournament matches (team-based): cùng tournament_id, khác team
        // team_members: chỉ có (team_id, user_id), không có match_id
        // teams: có tournament_id
        $tournamentMatches = DB::table('team_members as tm1')
            ->join('teams as t1', 'tm1.team_id', '=', 't1.id')
            ->join('teams as t2', function ($join) {
                $join->on('t1.tournament_id', '=', 't2.tournament_id')
                    ->on('t1.id', '!=', 't2.id');
            })
            ->join('team_members as tm2', 't2.id', '=', 'tm2.team_id')
            ->where('tm1.user_id', $userId)
            ->where('tm2.user_id', '!=', $userId)
            ->whereNotNull('t1.tournament_id')
            ->groupBy('tm2.user_id')
            ->select([
                'tm2.user_id',
                DB::raw('COUNT(DISTINCT t1.tournament_id) as tournaments_together'),
            ])
            ->get()
            ->keyBy('user_id');

        // Gộp tất cả counts và sort theo tổng matches
        // QUAN TRỌNG: các collection trên đều keyBy('user_id') nên phải gộp giá trị theo key
        // (không dùng Collection::merge — nó ghi đè row, mất count từ nguồn khác).
        $allCounts = []; // [uid => assoc array]
        foreach ([$playedWithCounts, $miniMatches, $miniParticipants, $tournamentMatches] as $src) {
            foreach ($src as $uid => $row) {
                $existing = $allCounts[$uid] ?? ['user_id' => $uid, 'matches_together' => 0, 'mini_tournaments_together' => 0, 'tournaments_together' => 0];
                if (isset($row->matches_together)) {
                    $existing['matches_together'] = (int) $row->matches_together;
                }
                if (isset($row->mini_tournaments_together)) {
                    $existing['mini_tournaments_together'] = (int) $row->mini_tournaments_together;
                }
                if (isset($row->mini_tournaments_participant)) {
                    $existing['mini_tournaments_together'] += (int) $row->mini_tournaments_participant;
                }
                if (isset($row->tournaments_together)) {
                    $existing['tournaments_together'] = (int) $row->tournaments_together;
                }
                $allCounts[$uid] = $existing;
            }
        }
        $userScores = $allCounts;

        // Sort theo tổng matches (desc) và lấy top N
        uasort($userScores, fn($a, $b) => (
            ($b['matches_together'] + $b['mini_tournaments_together'] + $b['tournaments_together'])
            <=> ($a['matches_together'] + $a['mini_tournaments_together'] + $a['tournaments_together'])
        ));
        $topUserIds = array_slice(array_keys($userScores), 0, self::GROUP_LIMIT);
        if (empty($topUserIds)) {
            return collect();
        }

        // Step 2: Load full User models
        $users = User::query()
            ->with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $topUserIds)
            ->where('id', '!=', $userId)
            ->where('is_banned', false)
            ->get();

        // Attach pre-computed counts
        foreach ($users as $user) {
            $counts = $userScores[$user->id] ?? [
                'matches_together' => 0,
                'mini_tournaments_together' => 0,
                'tournaments_together' => 0,
                'clubs_together' => 0,
            ];
            $user->setRelation('preloaded_relation', (object) [
                'matches_together' => (int) ($counts['matches_together'] ?? 0),
                'mini_tournaments_together' => (int) ($counts['mini_tournaments_together'] ?? 0),
                'tournaments_together' => (int) ($counts['tournaments_together'] ?? 0),
                'clubs_together' => 0,
            ]);
            $user->category = 'played_tournament';
            $user->category_text = 'Hay chơi cùng kèo giải';
        }

        // Sort lại theo thứ tự đã tính
        $sorted = new EloquentCollection();
        foreach ($topUserIds as $uid) {
            $user = $users->firstWhere('id', $uid);
            if ($user) {
                $sorted->push($user);
            }
        }

        return $sorted;
    }

    /**
     * Group 2: Users cùng CLB với current user.
     */
    private function querySameClub(int $userId): Collection
    {
        // Lấy club IDs của current user (status=active, chưa bị xóa)
        $myClubIds = DB::table('club_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('club_id')
            ->toArray();

        if (empty($myClubIds)) {
            return collect();
        }

        // Đếm số CLB chung với mỗi user
        $sharedClubs = DB::table('club_members as cm1')
            ->join('club_members as cm2', 'cm1.club_id', '=', 'cm2.club_id')
            ->whereIn('cm1.club_id', $myClubIds)
            ->where('cm1.user_id', $userId)
            ->where('cm2.user_id', '!=', $userId)
            ->where('cm2.status', 'active')
            ->whereNull('cm2.deleted_at')
            ->groupBy('cm2.user_id')
            ->select([
                'cm2.user_id',
                DB::raw('COUNT(DISTINCT cm1.club_id) as clubs_together'),
            ])
            ->get()
            ->keyBy('user_id');

        $userIds = $sharedClubs->pluck('user_id')->take(self::GROUP_LIMIT)->toArray();

        if (empty($userIds)) {
            return collect();
        }

        // Load full User models
        $users = User::query()
            ->with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->where('is_banned', false)
            ->get();

        // Attach pre-computed counts
        foreach ($users as $user) {
            $clubsTogether = $sharedClubs[$user->id]->clubs_together ?? 0;
            $user->setRelation('preloaded_relation', (object) [
                'matches_together' => 0,
                'mini_tournaments_together' => 0,
                'tournaments_together' => 0,
                'clubs_together' => (int) $clubsTogether,
            ]);
            $user->category = 'same_club';
            $user->category_text = 'Cùng CLB';
        }

        // Sort theo clubs_together desc
        return $users->sortByDesc(fn($u) => $u->preloaded_relation->clubs_together)->values();
    }

    /**
     * Group 3: Users ở gần vị trí (lat, lng) của current user.
     */
    private function queryNearby(int $userId, float $lat, float $lng, float $radiusKm): Collection
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

        $rows = DB::table('users')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->where('id', '!=', $userId)
            ->where('is_banned', false)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance')
            ->limit(self::GROUP_LIMIT)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        // Re-load qua Eloquent để có relations đầy đủ
        $userIds = $rows->pluck('id')->toArray();

        $users = User::query()
            ->with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->get();

        $result = new EloquentCollection();
        foreach ($userIds as $id) {
            $user = $users->firstWhere('id', $id);
            if ($user) {
                $user->setRelation('preloaded_relation', (object) [
                    'matches_together' => 0,
                    'mini_tournaments_together' => 0,
                    'tournaments_together' => 0,
                    'clubs_together' => 0,
                ]);
                $user->category = 'nearby';
                $user->category_text = 'Ở gần bạn';
                $result->push($user);
            }
        }

        return $result;
    }

    /**
     * Compute relation counts cho một collection user.
     * Dùng cho sub_tab=played_together (flat list) — reuse logic từ queryPlayedTogether.
     *
     * @return array uid => ['matches_together' => int, 'mini_tournaments_together' => int, 'tournaments_together' => int]
     */
    public function getPlayedTogetherRelations(int $userId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userIds = array_filter($userIds, fn($id) => $id != $userId);

        // Quick matches
        $quick = DB::table('match_histories as mh')
            ->join('match_histories as mh_other', 'mh_other.quick_match_id', '=', 'mh.quick_match_id')
            ->where('mh.user_id', $userId)
            ->whereIn('mh_other.user_id', $userIds)
            ->groupBy('mh_other.user_id')
            ->select('mh_other.user_id', DB::raw('COUNT(DISTINCT mh.quick_match_id) as matches_together'))
            ->get()
            ->keyBy('user_id');

        // Mini team
        $miniTeam = DB::table('mini_team_members as mtm1')
            ->join('mini_teams as mt1', 'mtm1.mini_team_id', '=', 'mt1.id')
            ->join('mini_teams as mt2', 'mt1.mini_tournament_id', '=', 'mt2.mini_tournament_id')
            ->join('mini_team_members as mtm2', 'mt2.id', '=', 'mtm2.mini_team_id')
            ->where('mtm1.user_id', $userId)
            ->whereIn('mtm2.user_id', $userIds)
            ->whereColumn('mtm1.mini_team_id', '!=', 'mtm2.mini_team_id')
            ->groupBy('mtm2.user_id')
            ->select('mtm2.user_id', DB::raw('COUNT(DISTINCT mt1.mini_tournament_id) as mini_tournaments_together'))
            ->get()
            ->keyBy('user_id');

        // Mini solo participant
        $miniSolo = DB::table('mini_participants as mp1')
            ->join('mini_matches as mm', function ($j) {
                $j->on('mp1.mini_tournament_id', '=', 'mm.mini_tournament_id')
                    ->on(fn($q) => $q->whereColumn('mp1.id', '=', 'mm.participant1_id')
                        ->orWhereColumn('mp1.id', '=', 'mm.participant2_id'));
            })
            ->join('mini_participants as mp2', fn($j) => $j->on('mp2.mini_tournament_id', '=', 'mp1.mini_tournament_id')
                ->where('mp2.user_id', '!=', $userId))
            ->where('mp1.user_id', $userId)
            ->whereIn('mp2.user_id', $userIds)
            ->groupBy('mp2.user_id')
            ->select('mp2.user_id', DB::raw('COUNT(DISTINCT mp1.mini_tournament_id) as mini_tournaments_participant'))
            ->get()
            ->keyBy('user_id');

        // Tournament
        $tour = DB::table('team_members as tm1')
            ->join('teams as t1', 'tm1.team_id', '=', 't1.id')
            ->join('teams as t2', fn($j) => $j->on('t1.tournament_id', '=', 't2.tournament_id')
                ->on('t1.id', '!=', 't2.id'))
            ->join('team_members as tm2', 't2.id', '=', 'tm2.team_id')
            ->where('tm1.user_id', $userId)
            ->whereIn('tm2.user_id', $userIds)
            ->whereNotNull('t1.tournament_id')
            ->groupBy('tm2.user_id')
            ->select('tm2.user_id', DB::raw('COUNT(DISTINCT t1.tournament_id) as tournaments_together'))
            ->get()
            ->keyBy('user_id');

        // Merge giống queryPlayedTogether
        $relations = [];
        foreach ([$quick, $miniTeam, $miniSolo, $tour] as $src) {
            foreach ($src as $uid => $row) {
                $r = $relations[$uid] ?? ['matches_together' => 0, 'mini_tournaments_together' => 0, 'tournaments_together' => 0];
                if (isset($row->matches_together)) {
                    $r['matches_together'] = (int) $row->matches_together;
                }
                if (isset($row->mini_tournaments_together)) {
                    $r['mini_tournaments_together'] = (int) $row->mini_tournaments_together;
                }
                if (isset($row->mini_tournaments_participant)) {
                    $r['mini_tournaments_together'] += (int) $row->mini_tournaments_participant;
                }
                if (isset($row->tournaments_together)) {
                    $r['tournaments_together'] = (int) $row->tournaments_together;
                }
                $relations[$uid] = $r;
            }
        }

        return $relations;
    }

    /**
     * Compute relation counts cho một collection user model.
     * Attach preloaded_relation lên mỗi user.
     */
    public function attachPlayedTogetherRelations(int $userId, $users): void
    {
        if ($users instanceof \Illuminate\Support\Collection) {
            $users = $users->all();
        }
        if (empty($users)) {
            return;
        }
        $userIds = array_column($users, 'id');
        $relations = $this->getPlayedTogetherRelations($userId, $userIds);
        foreach ($users as $user) {
            $rel = $relations[$user->id] ?? ['matches_together' => 0, 'mini_tournaments_together' => 0, 'tournaments_together' => 0, 'clubs_together' => 0];
            $user->setRelation('preloaded_relation', (object) $rel);
        }
    }

    /**
     * Attach relation data cho tất cả users (clubs_together).
     * Tính clubs_together cho từng user.
     */
    private function attachRelations(EloquentCollection $users, int $userId): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->toArray();
        $myClubIds = DB::table('club_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->pluck('club_id')
            ->toArray();

        // Nếu current user không có CLB nào, bỏ qua clubs_together
        if (empty($myClubIds)) {
            return;
        }

        // Đếm clubs chung cho các user đã có preloaded_relation
        $sharedClubs = DB::table('club_members')
            ->whereIn('club_id', $myClubIds)
            ->whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->groupBy('user_id')
            ->select([
                'user_id',
                DB::raw('COUNT(*) as clubs_together'),
            ])
            ->get()
            ->keyBy('user_id');

        foreach ($users as $user) {
            if ($user->preloaded_relation) {
                $relation = (array) $user->preloaded_relation;
                $relation['clubs_together'] = $sharedClubs[$user->id]->clubs_together ?? 0;
                $user->setRelation('preloaded_relation', (object) $relation);
            }
        }
    }

    /**
     * Compute Haversine distance cho tất cả users trong 3 nhóm.
     * Đảm bảo mọi user đều có distance khi có lat/lng anchor.
     */
    private function computeDistances(array $result, float $lat, float $lng): void
    {
        $haversine = "(6371 * acos(
            LEAST(1.0, GREATEST(-1.0,
                cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                + sin(radians(?)) * sin(radians(latitude))
            ))
        ))";

        $allIds = $result['played_tournament']->pluck('id')
            ->merge($result['same_club']->pluck('id'))
            ->merge($result['nearby']->pluck('id'))
            ->toArray();

        if (empty($allIds)) {
            return;
        }

        $distances = DB::table('users')
            ->whereIn('id', $allIds)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->get()
            ->keyBy('id')
            ->map(fn($r) => round((float) $r->distance, 3));

        $setDist = function ($user) use ($distances) {
            $user->distance = $distances[$user->id] ?? null;
        };

        $result['played_tournament']->each($setDist);
        $result['same_club']->each($setDist);
        $result['nearby']->each($setDist);
    }
}
