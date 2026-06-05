<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // club_members: composite index for visibleFor scope (same club membership check)
        // and batch membership queries (where user_id IN ... AND membership_status = 'joined')
        Schema::table('club_members', function (Blueprint $table) {
            if (! $this->indexExists('club_members', 'idx_club_members_user_club_status')) {
                $table->index(
                    ['user_id', 'membership_status', 'status', 'club_id'],
                    'idx_club_members_user_club_status'
                );
            }
            if (! $this->indexExists('club_members', 'idx_club_members_club_user')) {
                $table->index(['club_id', 'user_id'], 'idx_club_members_club_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            if ($this->indexExists('club_members', 'idx_club_members_user_club_status')) {
                $table->dropIndex('idx_club_members_user_club_status');
            }
            if ($this->indexExists('club_members', 'idx_club_members_club_user')) {
                $table->dropIndex('idx_club_members_club_user');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
