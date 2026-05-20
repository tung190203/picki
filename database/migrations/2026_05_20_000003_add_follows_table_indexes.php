<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Critical indexes for follows table:
        // - visibleFor scope: WHERE user_id = ? (self-follow check + friend mutual follow check)
        // - friends() method: WHERE followable_id = ? (reverse follow lookup)
        // - followable_type filter: WHERE followable_type = ? (User vs Club vs Team)
        Schema::table('follows', function (Blueprint $table) {
            if (! $this->indexExists('follows', 'idx_follows_user')) {
                $table->index('user_id', 'idx_follows_user');
            }
            if (! $this->indexExists('follows', 'idx_follows_followable')) {
                $table->index('followable_id', 'idx_follows_followable');
            }
            if (! $this->indexExists('follows', 'idx_follows_type')) {
                $table->index('followable_type', 'idx_follows_type');
            }
            // Composite for common filter: user_id + followable_type (most queries in visibleFor/friends)
            if (! $this->indexExists('follows', 'idx_follows_user_type')) {
                $table->index(['user_id', 'followable_type'], 'idx_follows_user_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('idx_follows_user');
            $table->dropIndex('idx_follows_followable');
            $table->dropIndex('idx_follows_type');
            $table->dropIndex('idx_follows_user_type');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
