<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite index supporting admin/users list query:
     *   WHERE is_guest = 0 [AND is_banned = ?] ORDER BY last_active_at DESC
     *
     * The existing single-column index on `last_active_at` cannot be used when
     * filtering on `is_guest` (MySQL chooses the wrong index). This composite
     * index lets the optimizer satisfy the WHERE filter AND the ORDER BY
     * without a filesort on large users tables.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $indexName = 'idx_users_admin_users_list';
            $exists = collect(DB::select(
                "SHOW INDEX FROM users WHERE Key_name = ?",
                [$indexName]
            ))->isNotEmpty();

            if (!$exists) {
                $table->index(['is_guest', 'last_active_at'], $indexName);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_admin_users_list');
        });
    }
};