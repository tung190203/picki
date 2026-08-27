<?php

namespace App\Services\Permission;

use App\Models\Tournament;
use App\Models\TournamentStaff;

/**
 * Permission matrix cho Tournament (Giải đấu).
 *
 * Role mapping:
 *  - Admin    = ROLE_ORGANIZER (1)
 *  - BTC      = ROLE_STAFF     (2)
 *  - Trọng tài = ROLE_REFEREE   (3)
 *
 * Trọng tài có thể bị giới hạn theo `court_id` (scope theo sân).
 *
 * NOTE: Tournament đã có sẵn 3 role khớp với Flutter enum, không cần data migration.
 * Method canAssignRole giữ behavior hiện tại (chỉ Admin gán) — nếu sau muốn BTC
 * cũng gán được Trọng tài cho Giải đấu thì điều chỉnh tại đây.
 */
class TournamentPermission
{
    public static function canEditRules(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canPublish(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canCloseRegistration(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canStart(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canFinish(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canDelete(Tournament $t, int $userId): bool
    {
        return $t->hasOrganizer($userId);
    }

    public static function canManageAthletes(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canOperateBracket(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    /**
     * Quyền nhập điểm trận đấu.
     *
     * Trọng tài chỉ được score trận thuộc court được gán (nếu có gán).
     * Nếu không truyền courtId, kiểm tra quyền cơ bản (Admin/BTC/Referee tồn tại).
     */
    public static function canScore(Tournament $t, int $userId, ?int $courtId = null): bool
    {
        if ($t->hasOrganizer($userId) || $t->hasOrganizerOrStaff($userId)) {
            return true;
        }

        $referee = $t->tournamentStaffs()
            ->where('user_id', $userId)
            ->where('role', TournamentStaff::ROLE_REFEREE)
            ->first();

        if (!$referee) {
            return false;
        }

        // Trọng tài không giới hạn court (court_id null) → được score mọi sân
        if ($referee->court_id === null) {
            return true;
        }

        // Trọng tài có court_id → chỉ được score đúng court đó
        if ($courtId !== null && (int) $referee->court_id === $courtId) {
            return true;
        }

        return false;
    }

    public static function canManageFinance(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    public static function canManageSponsorship(Tournament $t, int $userId): bool
    {
        return self::isAdminOrBtc($t, $userId);
    }

    /**
     * Quyền gán vai trò. Hiện tại chỉ Admin được gán — giữ behavior cũ.
     * (Có thể mở rộng sau: BTC gán được Trọng tài, tương tự MiniTournament.)
     */
    public static function canAssignRole(
        Tournament $t,
        int $userId,
        ?int $targetRole = null
    ): bool {
        return $t->hasOrganizer($userId);
    }

    public static function canRevokeRole(
        Tournament $t,
        int $userId,
        ?int $targetRole = null
    ): bool {
        return $t->hasOrganizer($userId);
    }

    protected static function isAdminOrBtc(Tournament $t, int $userId): bool
    {
        return $t->hasOrganizerOrStaff($userId);
    }
}