<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cho phép thêm thành viên ảo (ClubVirtualMember) làm BTC / Trọng tài của giải đấu.
 *
 * Trước đây `tournament_staff.user_id` là NOT NULL + FK `users.id`, nên không thể
 * lưu thành viên ảo (VM không có bản ghi trong bảng `users`).
 *
 * Thay đổi:
 *  - `user_id` → nullable, bỏ FK constraint (VM không có user row)
 *  - thêm cột snapshot để render VM trong danh sách ban tổ chức:
 *      is_virtual         : boolean, đánh dấu bản ghi là thành viên ảo
 *      virtual_member_id  : club_virtual_members.id (null nếu là user thật)
 *      guest_name         : snapshot `club_virtual_members.name` tại thời điểm thêm
 *      guest_avatar       : snapshot `club_virtual_members.avatar_url` tại thời điểm thêm
 *  - unique constraint cũ `[tournament_id, user_id, role]` không chặn được VM
 *    (NULL luôn khác NULL trong MySQL unique index), nên thêm unique mới theo
 *    `virtual_member_id` cho VM và giữ unique cũ cho user thật.
 *
 * Ghi chú: unique `[tournament_id, user_id, role]` vẫn được giữ nguyên để không
 * ảnh hưởng dữ liệu user thật đang tồn tại. Ràng buộc "1 VM chỉ 1 role / giải"
 * được enforce ở tầng application (controller) vì MySQL không cho partial index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
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

        // 4. Unique riêng cho thành viên ảo (user_id = NULL sẽ bypass unique cũ)
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->unique(
                ['tournament_id', 'virtual_member_id', 'role'],
                'tournament_staff_virtual_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->dropUnique('tournament_staff_virtual_unique');

            $table->dropColumn(['is_virtual', 'virtual_member_id', 'guest_name', 'guest_avatar']);

            // Khôi phục NOT NULL + FK. Các bản ghi thành viên ảo sẽ không rollback được.
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
