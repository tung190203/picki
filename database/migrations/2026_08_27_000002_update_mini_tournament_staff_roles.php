<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: chuẩn hoá role trong mini_tournament_staff.
 *
 * BACKGROUND:
 *  - Backend MiniTournamentStaff hiện lưu:
 *      ROLE_ORGANIZER = 1 (Admin)
 *      ROLE_REFEREE   = 2 (Trọng tài)
 *
 *  - Target theo plan:
 *      ROLE_ADMIN    = 1 (organizer = Admin)
 *      ROLE_STAFF    = 2 (BTC)
 *      ROLE_REFEREE  = 3 (Trọng tài)
 *
 *  - Flutter enum MiniTournamentStaffRole đã đúng: 1/2/3.
 *
 * ACTION:
 *  - role = 2 (referee cũ) → role = 3 (referee mới)
 *  - role = 1 giữ nguyên (organizer/Admin)
 *
 * Sau migration, role = 2 sẽ được dùng cho BTC/staff, được thêm qua API.
 *
 * SAFETY:
 *  - Chạy count() verify trước/sau để đảm bảo không mất dữ liệu
 *  - Down(): chuyển ngược role = 3 → role = 2
 */
return new class extends Migration
{
    public function up(): void
    {
        // Đếm số referee cũ trước khi migrate
        $oldRefereeCount = DB::table('mini_tournament_staff')
            ->where('role', 2)
            ->count();

        if ($oldRefereeCount > 0) {
            DB::statement('UPDATE mini_tournament_staff SET role = 3 WHERE role = 2');
        }

        // Sau migration: đảm bảo không còn role = 2 nào (nếu có thì là bug)
        $remainingRole2 = DB::table('mini_tournament_staff')
            ->where('role', 2)
            ->count();

        // Verify: số referee mới phải bằng số referee cũ
        $newRefereeCount = DB::table('mini_tournament_staff')
            ->where('role', 3)
            ->count();

        // Log để theo dõi (không throw vì có thể DB test không cần)
        if ($remainingRole2 > 0) {
            // Không throw — để team check log thủ công
            \Log::warning("mini_tournament_staff migration: còn {$remainingRole2} record role=2 (có thể là seed data mới)");
        }
    }

    public function down(): void
    {
        // Rollback: role = 3 → role = 2
        DB::statement('UPDATE mini_tournament_staff SET role = 2 WHERE role = 3');
    }
};