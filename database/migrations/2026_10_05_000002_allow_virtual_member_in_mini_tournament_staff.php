<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép thêm thành viên ảo (ClubVirtualMember) làm BTC / Trọng tài của mini-tournament.
 *
 * Giống migration `2026_10_05_000001_allow_virtual_member_in_tournament_staff.php` cho
 * bảng `tournament_staff`:
 *  - `user_id` → nullable, bỏ FK constraint (VM không có bản ghi trong `users`)
 *  - thêm cột snapshot: is_virtual, virtual_member_id, guest_name, guest_avatar
 *  - unique mới `[tournament_id(mini), virtual_member_id, role]` vì NULL bypass unique cũ
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournament_staff', function (Blueprint $table) {
            // 1. Bỏ FK constraint trước khi cho phép NULL
            $table->dropForeign(['user_id']);

            // 2. Cho phép user_id = null (dành cho thành viên ảo)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // 3. Cột đánh dấu + snapshot thành viên ảo
            $table->boolean('is_virtual')->default(false)->after('user_id');
            $table->unsignedBigInteger('virtual_member_id')->nullable()->after('is_virtual');
            $table->string('guest_name')->nullable()->after('virtual_member_id');
            $table->string('guest_avatar')->nullable()->after('guest_name');
        });

        // 4. Unique riêng cho thành viên ảo
        Schema::table('mini_tournament_staff', function (Blueprint $table) {
            $table->unique(
                ['mini_tournament_id', 'virtual_member_id', 'role'],
                'mini_tournament_staff_virtual_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournament_staff', function (Blueprint $table) {
            $table->dropUnique('mini_tournament_staff_virtual_unique');

            $table->dropColumn(['is_virtual', 'virtual_member_id', 'guest_name', 'guest_avatar']);

            // Khôi phục NOT NULL + FK. Bản ghi thành viên ảo sẽ không rollback được.
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
