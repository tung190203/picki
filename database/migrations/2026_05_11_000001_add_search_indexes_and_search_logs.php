<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- search_logs table ---
        if (! Schema::hasTable('search_logs')) {
            Schema::create('search_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tab', 20)->comment('match, tournament, club, user, court');
                $table->string('keyword', 255)->nullable();
                $table->string('filters_json', 500)->nullable();
                $table->string('sub_tab', 20)->nullable();
                $table->string('result_count', 20)->nullable()->comment('cached approximate count');
                $table->timestamp('searched_at')->useCurrent();
                $table->index(['tab', 'searched_at']);
                $table->index('user_id');
            });
        }

        // --- tournaments: composite index for timeline queries ---
        if (! $this->indexExists('tournaments', 'idx_tournaments_timeline')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->index(['start_date', 'status'], 'idx_tournaments_timeline');
            });
        }
        if (! $this->indexExists('tournaments', 'idx_tournaments_sport_date')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->index(['sport_id', 'start_date'], 'idx_tournaments_sport_date');
            });
        }

        // --- mini_tournaments: composite index for timeline queries ---
        if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_timeline')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->index(['start_time', 'status'], 'idx_mini_tournaments_timeline');
            });
        }
        if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_sport_date')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->index(['sport_id', 'start_time'], 'idx_mini_tournaments_sport_date');
            });
        }

        // --- users: last active + visibility index ---
        if (! $this->indexExists('users', 'idx_users_active_visibility')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['last_active_at', 'visibility'], 'idx_users_active_visibility');
            });
        }
        if (! $this->indexExists('users', 'idx_users_visibility') && ! $this->indexExists('users', 'users_visibility_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('visibility', 'idx_users_visibility');
            });
        }

        // --- clubs: geo composite ---
        if (! $this->indexExists('clubs', 'idx_clubs_geo')) {
            Schema::table('clubs', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'idx_clubs_geo');
            });
        }

        // --- competition_locations: geo composite ---
        if (! $this->indexExists('competition_locations', 'idx_comp_locations_geo')) {
            Schema::table('competition_locations', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'idx_comp_locations_geo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');

        Schema::table('tournaments', function (Blueprint $table) {
            if ($this->indexExists('tournaments', 'idx_tournaments_timeline')) {
                $table->dropIndex('idx_tournaments_timeline');
            }
            if ($this->indexExists('tournaments', 'idx_tournaments_sport_date')) {
                $table->dropIndex('idx_tournaments_sport_date');
            }
        });

        Schema::table('mini_tournaments', function (Blueprint $table) {
            if ($this->indexExists('mini_tournaments', 'idx_mini_tournaments_timeline')) {
                $table->dropIndex('idx_mini_tournaments_timeline');
            }
            if ($this->indexExists('mini_tournaments', 'idx_mini_tournaments_sport_date')) {
                $table->dropIndex('idx_mini_tournaments_sport_date');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_active_visibility')) {
                $table->dropIndex('idx_users_active_visibility');
            }
            if ($this->indexExists('users', 'idx_users_visibility')) {
                $table->dropIndex('idx_users_visibility');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if ($this->indexExists('clubs', 'idx_clubs_geo')) {
                $table->dropIndex('idx_clubs_geo');
            }
        });

        Schema::table('competition_locations', function (Blueprint $table) {
            if ($this->indexExists('competition_locations', 'idx_comp_locations_geo')) {
                $table->dropIndex('idx_comp_locations_geo');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
