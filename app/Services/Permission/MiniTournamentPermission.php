<?php

namespace App\Services\Permission;

use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;

/**
 * Permission matrix cho MiniTournament (Kèo đấu).
 *
 * Role mapping:
 *  - Admin    = ROLE_ADMIN   (= ROLE_ORGANIZER = 1)
 *  - BTC      = ROLE_STAFF   (= 2)
 *  - Trọng tài = ROLE_REFEREE (= 3)
 *
 * Quy tắc (đã chốt với Thắng):
 *  - Admin: gán/thu hồi được mọi role (Admin, BTC, Trọng tài)
 *  - BTC: chỉ gán/thu hồi được Trọng tài
 *  - Trọng tài: ❌ không gán/thu hồi role nào
 */
class MiniTournamentPermission
{
    // === Vòng đời kèo ===

    public static function canEditRules(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canDelete(MiniTournament $t, int $userId): bool
    {
        return $t->hasAdmin($userId);
    }

    // === Người tham gia ===

    public static function canManageParticipants(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canCheckIn(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    // === Vận hành trận đấu ===

    public static function canOperateMatches(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canScore(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId) || $t->hasReferee($userId);
    }

    // === Tài chính ===

    public static function canManageFinance(MiniTournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    // === Vai trò ===

    /**
     * Quyền gán vai trò cho user khác.
     *
     * @param int|null $targetRole role muốn gán (1=Admin, 2=BTC, 3=Referee).
     *                             Nếu null → chỉ cần Admin/BTC cơ bản, không check target.
     */
    public static function canAssignRole(
        MiniTournament $t,
        int $userId,
        ?int $targetRole = null
    ): bool {
        if ($t->hasAdmin($userId)) {
            return true;
        }

        if ($t->hasBtc($userId) && $targetRole === MiniTournamentStaff::ROLE_REFEREE) {
            return true;
        }

        return false;
    }

    public static function canRevokeRole(
        MiniTournament $t,
        int $userId,
        ?int $targetRole = null
    ): bool {
        return self::canAssignRole($t, $userId, $targetRole);
    }

    // === Helpers ===

    protected static function isAdminOrBtc(MiniTournament $t, int $userId): bool
    {
        return $t->hasAdmin($userId) || $t->hasBtc($userId);
    }
}