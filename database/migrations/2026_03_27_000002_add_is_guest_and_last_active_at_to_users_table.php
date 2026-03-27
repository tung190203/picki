<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_guest')) {
                $table->boolean('is_guest')
                    ->default(false)
                    ->after('apple_id')
                    ->comment('true: la tai khoan guest duoc tao tu dong khi them guest vao keo dau, se bi xoa sau 7 ngay khong hoat dong');
            }

            if (!Schema::hasColumn('users', 'last_active_at')) {
                $table->timestamp('last_active_at')
                    ->nullable()
                    ->after('is_guest')
                    ->comment('Lan cuoi cung guest co hoat dong (login hoac update)');
            }
        });

        // Index để query guest không hoạt động nhanh
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['is_guest', 'last_active_at'], 'idx_users_guest_inactive');
            });
        } catch (\Exception $e) {
            // Index có thể đã tồn tại
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_active_at')) {
                $table->dropIndex('idx_users_guest_inactive');
                $table->dropColumn('last_active_at');
            }
            if (Schema::hasColumn('users', 'is_guest')) {
                $table->dropColumn('is_guest');
            }
        });
    }
};
