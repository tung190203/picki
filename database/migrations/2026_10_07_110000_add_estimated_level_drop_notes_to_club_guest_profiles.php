<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột `estimated_level` vào `club_guest_profiles` để lưu trình độ của CLB
 * guest (dùng khi invite vào event: snapshot xuống participants/mini_participants).
 *
 * Đồng thời xoá cột `notes` (không còn dùng — CLB quản lý guest qua các field
 * cốt lõi của User + Profile).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_guest_profiles', function (Blueprint $table) {
            $table->decimal('estimated_level', 3, 1)
                ->nullable()
                ->after('user_id')
                ->comment('Trình độ ước tính 1.0–8.0 (cover cả thang kèo và giải)');

            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('club_guest_profiles', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('user_id');
            $table->dropColumn('estimated_level');
        });
    }
};