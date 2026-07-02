<?php

namespace App\Services;

use App\Models\UserSport;

/**
 * Service quản lý counter total_matches trên user_sport.
 * Gọi increment/decrement khi match được tạo hoặc hoàn thành.
 */
class UserSportMatchCounter
{
    /**
     * Tăng counter cho tất cả user tham gia team.
     * Gọi khi match hoàn thành (status = 'completed').
     */
    public function incrementForTeam(int $teamId, int $sportId): void
    {
        $userIds = \DB::table('team_members')
            ->where('team_id', $teamId)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->increment('total_matches');
    }

    /**
     * Tăng counter cho tất cả member của mini_team.
     */
    public function incrementForMiniTeam(int $miniTeamId, int $sportId): void
    {
        $userIds = \DB::table('mini_team_members')
            ->where('mini_team_id', $miniTeamId)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->increment('total_matches');
    }

    /**
     * Tăng counter cho user tham gia quick_match (match_history).
     */
    public function incrementForQuickMatchUser(int $userId, int $sportId): void
    {
        UserSport::where('sport_id', $sportId)
            ->where('user_id', $userId)
            ->increment('total_matches');
    }

    /**
     * Giảm counter — gọi khi match bị hủy hoặc kết quả bị xóa.
     */
    public function decrementForTeam(int $teamId, int $sportId): void
    {
        $userIds = \DB::table('team_members')
            ->where('team_id', $teamId)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        UserSport::where('sport_id', $sportId)
            ->whereIn('user_id', $userIds)
            ->where('total_matches', '>', 0)
            ->decrement('total_matches');
    }

    public function decrementForMiniTeam(int $miniTeamId, int $sportId): void
    {
        $userIds = \DB::table('mini_team_members')
            ->where('mini_team_id', $miniTeamId)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
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
