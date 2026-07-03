<?php

namespace App\Services;

use App\Models\UserSport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Service quản lý counter total_matches trên user_sport.
 * Gọi increment/decrement khi match được tạo hoặc hoàn thành.
 */
class UserSportMatchCounter
{
    /**
     * Ensure user_sport records exist for all given user IDs.
     * Creates with total_matches = 0 if not exists.
     */
    private function ensureRecordsExist(array $userIds, int $sportId): void
    {
        $existing = UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->toArray();

        $missing = array_diff($userIds, $existing);
        if (empty($missing)) {
            Log::debug('[UserSportMatchCounter] All records exist, skipping insert', [
                'sport_id' => $sportId,
                'count' => count($userIds),
            ]);
            return;
        }

        $now = now();
        $inserts = [];
        foreach ($missing as $uid) {
            $inserts[] = [
                'user_id' => (int) $uid,
                'sport_id' => (int) $sportId,
                'total_matches' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        UserSport::insert($inserts);
        Log::info('[UserSportMatchCounter] Created user_sport records', [
            'count' => count($inserts),
            'sport_id' => $sportId,
            'missing_user_ids' => array_values($missing),
        ]);
    }

    /**
     * Tăng counter cho tất cả user tham gia team.
     * Gọi khi match hoàn thành (status = 'completed').
     */
    public function incrementForTeam(int $teamId, int $sportId): void
    {
        $userIds = DB::table('team_members')
            ->where('team_id', $teamId)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            Log::debug('[UserSportMatchCounter] incrementForTeam: no members in team', ['team_id' => $teamId]);
            return;
        }

        Log::info('[UserSportMatchCounter] incrementForTeam START', [
            'team_id' => $teamId,
            'sport_id' => $sportId,
            'member_count' => count($userIds),
            'member_ids' => $userIds,
        ]);

        $this->ensureRecordsExist($userIds, $sportId);

        $affected = UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->increment('total_matches');

        Log::info('[UserSportMatchCounter] incrementForTeam DONE', [
            'team_id' => $teamId,
            'sport_id' => $sportId,
            'affected' => $affected,
        ]);
    }

    /**
     * Tăng counter cho tất cả member của mini_team.
     */
    public function incrementForMiniTeam(int $miniTeamId, int $sportId): void
    {
        $userIds = DB::table('mini_team_members')
            ->where('mini_team_id', $miniTeamId)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            Log::debug('[UserSportMatchCounter] incrementForMiniTeam: no members in team', ['mini_team_id' => $miniTeamId]);
            return;
        }

        Log::info('[UserSportMatchCounter] incrementForMiniTeam START', [
            'mini_team_id' => $miniTeamId,
            'sport_id' => $sportId,
            'member_count' => count($userIds),
            'member_ids' => $userIds,
        ]);

        $this->ensureRecordsExist($userIds, $sportId);

        $affected = UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->increment('total_matches');

        Log::info('[UserSportMatchCounter] incrementForMiniTeam DONE', [
            'mini_team_id' => $miniTeamId,
            'sport_id' => $sportId,
            'affected' => $affected,
        ]);
    }

    /**
     * Tăng counter cho user tham gia quick_match (match_history).
     */
    public function incrementForQuickMatchUser(int $userId, int $sportId): void
    {
        Log::info('[UserSportMatchCounter] incrementForQuickMatchUser START', [
            'user_id' => $userId,
            'sport_id' => $sportId,
        ]);

        $this->ensureRecordsExist([$userId], $sportId);

        $affected = UserSport::where('sport_id', $sportId)
            ->where('user_id', $userId)
            ->increment('total_matches');

        Log::info('[UserSportMatchCounter] incrementForQuickMatchUser DONE', [
            'user_id' => $userId,
            'sport_id' => $sportId,
            'affected' => $affected,
        ]);
    }

    /**
     * Giảm counter — gọi khi match bị hủy hoặc kết quả bị xóa.
     */
    public function decrementForTeam(int $teamId, int $sportId): void
    {
        $userIds = DB::table('team_members')
            ->where('team_id', $teamId)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            return;
        }

        UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->where('total_matches', '>', 0)
            ->decrement('total_matches');
    }

    public function decrementForMiniTeam(int $miniTeamId, int $sportId): void
    {
        $userIds = DB::table('mini_team_members')
            ->where('mini_team_id', $miniTeamId)
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            return;
        }

        UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->where('total_matches', '>', 0)
            ->decrement('total_matches');
    }

    public function decrementForQuickMatchUser(int $userId, int $sportId): void
    {
        UserSport::where('sport_id', $sportId)
            ->where('user_id', $userId)
            ->where('total_matches', '>', 0)
            ->decrement('total_matches');
    }
}
