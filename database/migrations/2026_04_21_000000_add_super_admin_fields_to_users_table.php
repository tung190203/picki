<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false)->after('role');
            }
            if (!Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('is_super_admin');
            }
            if (!Schema::hasColumn('users', 'is_anchor')) {
                $table->boolean('is_anchor')->default(false)->after('is_verified');
            }
            if (!Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false)->after('is_anchor');
            }
            if (!Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('is_banned');
            }
            if (!Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason')->nullable()->after('banned_at');
            }
            if (!Schema::hasColumn('users', 'banned_by')) {
                $table->unsignedBigInteger('banned_by')->nullable()->after('ban_reason');
            }
            if (!Schema::hasColumn('users', 'ban_note')) {
                $table->text('ban_note')->nullable()->after('banned_by');
            }
            if (!Schema::hasColumn('users', 'trust_score')) {
                $table->float('trust_score')->default(0)->after('last_active_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'trust_score')) {
                $table->dropColumn('trust_score');
            }
            if (Schema::hasColumn('users', 'ban_note')) {
                $table->dropColumn('ban_note');
            }
            if (Schema::hasColumn('users', 'banned_by')) {
                $table->dropColumn('banned_by');
            }
            if (Schema::hasColumn('users', 'ban_reason')) {
                $table->dropColumn('ban_reason');
            }
            if (Schema::hasColumn('users', 'banned_at')) {
                $table->dropColumn('banned_at');
            }
            if (Schema::hasColumn('users', 'is_banned')) {
                $table->dropColumn('is_banned');
            }
            if (Schema::hasColumn('users', 'is_anchor')) {
                $table->dropColumn('is_anchor');
            }
            if (Schema::hasColumn('users', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
            if (Schema::hasColumn('users', 'is_super_admin')) {
                $table->dropColumn('is_super_admin');
            }
        });
    }
};
