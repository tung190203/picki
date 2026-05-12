<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clubs geo index
        if (! $this->indexExists('clubs', 'idx_clubs_geo')) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'idx_clubs_geo');
            });
        }

        // Competition locations geo index
        if (! $this->indexExists('competition_locations', 'idx_comp_locations_geo')) {
            Schema::table('competition_locations', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'idx_comp_locations_geo');
            });
        }

        // Users: last active + visibility index
        if (! $this->indexExists('users', 'idx_users_active_visibility')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['last_active_at', 'visibility'], 'idx_users_active_visibility');
            });
        }

        // visibility index — may already exist with auto-generated name
        if (! $this->indexExists('users', 'idx_users_visibility') && ! $this->indexExists('users', 'users_visibility_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('visibility', 'idx_users_visibility');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex('idx_clubs_geo');
        });

        Schema::table('competition_locations', function (Blueprint $table) {
            $table->dropIndex('idx_comp_locations_geo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_active_visibility');
            if ($this->indexExists('users', 'idx_users_visibility')) {
                $table->dropIndex('idx_users_visibility');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'")) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
