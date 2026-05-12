<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- search_logs table ---
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tab', 20)->comment('match, tournament, club, user, court');
            $table->string('keyword', 255)->nullable();
            $table->string('filters_json', 500)->nullable();
            $table->string('time_filter', 20)->nullable();
            $table->string('result_count', 20)->nullable()->comment('cached approximate count');
            $table->timestamp('searched_at')->useCurrent();
            $table->index(['tab', 'searched_at']);
            $table->index('user_id');
        });

        // --- tournaments: composite index for timeline queries ---
        Schema::table('tournaments', function (Blueprint $table) {
            $table->index(['start_date', 'status'], 'idx_tournaments_timeline');
            $table->index(['sport_id', 'start_date'], 'idx_tournaments_sport_date');
        });

        // --- mini_tournaments: composite index for timeline queries ---
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->index(['start_time', 'status'], 'idx_mini_tournaments_timeline');
            $table->index(['sport_id', 'start_time'], 'idx_mini_tournaments_sport_date');
        });

        // --- users: online status + last active index ---
        Schema::table('users', function (Blueprint $table) {
            $table->index(['is_online', 'last_active_at'], 'idx_users_online_active');
            $table->index('visibility');
        });

        // --- clubs: geo composite (already added elsewhere but ensure it exists) ---
        Schema::table('clubs', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'idx_clubs_geo');
        });

        // --- competition_locations: geo composite ---
        Schema::table('competition_locations', function (Blueprint $table) {
            $table->index(['latitude', 'longitude'], 'idx_comp_locations_geo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_tournaments_timeline');
            $table->dropIndex('idx_tournaments_sport_date');
        });

        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_mini_tournaments_timeline');
            $table->dropIndex('idx_mini_tournaments_sport_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_online_active');
            $table->dropIndex('idx_users_visibility');
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex('idx_clubs_geo');
        });

        Schema::table('competition_locations', function (Blueprint $table) {
            $table->dropIndex('idx_comp_locations_geo');
        });
    }
};
