<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop cột `is_virtual`, `virtual_member_id` trên `tournament_staff` và
 * `mini_tournament_staff`. CLB guest giờ là user thật (User.is_guest=true) nên
 * không cần 2 cột này nữa. `guest_name`/`guest_avatar` được giữ lại làm
 * snapshot lịch sử.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tournament_staff', 'mini_tournament_staff'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Drop unique cũ trên virtual_member_id nếu có (MySQL cần drop trước khi drop col)
            $uniqueVirtual = "{$table}_virtual_unique";
            try {
                Schema::table($table, function (Blueprint $t) use ($uniqueVirtual) {
                    $t->dropUnique($uniqueVirtual);
                });
            } catch (\Throwable $e) {
                // ignore nếu index không tồn tại (DB khác / production cũ)
            }

            if (Schema::hasColumn($table, 'is_virtual')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('is_virtual');
                });
            }
            if (Schema::hasColumn($table, 'virtual_member_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('virtual_member_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tournament_staff', 'mini_tournament_staff'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'is_virtual')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_virtual')->default(false)->after('user_id');
                });
            }
            if (!Schema::hasColumn($table, 'virtual_member_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('virtual_member_id')->nullable()->after('is_virtual');
                });
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->unique(
                        ['tournament_id', 'virtual_member_id', 'role'],
                        "{$table}_virtual_unique"
                    );
                });
            }
        }
    }
};
