<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('mini_tournaments', 'set_number')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('set_number')->nullable()->change();
            });
        }

        // Backward compatible: some databases still have games_per_set before it is renamed to base_points.
        if (Schema::hasColumn('mini_tournaments', 'base_points')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('base_points')->nullable()->change();
            });
        } elseif (Schema::hasColumn('mini_tournaments', 'games_per_set')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('games_per_set')->nullable()->change();
            });
        }

        if (Schema::hasColumn('mini_tournaments', 'points_difference')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('points_difference')->nullable()->change();
            });
        }

        if (Schema::hasColumn('mini_tournaments', 'max_points')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('max_points')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mini_tournaments', 'set_number')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('set_number')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('mini_tournaments', 'base_points')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('base_points')->nullable(false)->change();
            });
        } elseif (Schema::hasColumn('mini_tournaments', 'games_per_set')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('games_per_set')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('mini_tournaments', 'points_difference')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('points_difference')->nullable(false)->change();
            });
        }

        if (Schema::hasColumn('mini_tournaments', 'max_points')) {
            Schema::table('mini_tournaments', function (Blueprint $table) {
                $table->unsignedInteger('max_points')->nullable(false)->change();
            });
        }
    }
};
