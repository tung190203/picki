<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // matches table: critical FK indexes for tournament match queries
        Schema::table('matches', function (Blueprint $table) {
            if (! $this->indexExists('matches', 'idx_matches_home_team')) {
                $table->index('home_team_id', 'idx_matches_home_team');
            }
            if (! $this->indexExists('matches', 'idx_matches_away_team')) {
                $table->index('away_team_id', 'idx_matches_away_team');
            }
            if (! $this->indexExists('matches', 'idx_matches_winner')) {
                $table->index('winner_id', 'idx_matches_winner');
            }
            if (! $this->indexExists('matches', 'idx_matches_tournament_type')) {
                $table->index('tournament_type_id', 'idx_matches_tournament_type');
            }
            if (! $this->indexExists('matches', 'idx_matches_group')) {
                $table->index('group_id', 'idx_matches_group');
            }
            if (! $this->indexExists('matches', 'idx_matches_status')) {
                $table->index('status', 'idx_matches_status');
            }
        });

        // team_members: index on team_id for member lookup
        Schema::table('team_members', function (Blueprint $table) {
            if (! $this->indexExists('team_members', 'idx_team_members_team')) {
                $table->index('team_id', 'idx_team_members_team');
            }
        });

        // mini_team_members: index on mini_team_id
        Schema::table('mini_team_members', function (Blueprint $table) {
            if (! $this->indexExists('mini_team_members', 'idx_mini_team_members_team')) {
                $table->index('mini_team_id', 'idx_mini_team_members_team');
            }
        });

        // user_sport_scores: composite index for vndupr rating lookups
        Schema::table('user_sport_scores', function (Blueprint $table) {
            if (! $this->indexExists('user_sport_scores', 'idx_uss_user_sport_score')) {
                $table->index(['user_sport_id', 'score_type', 'score_value'], 'idx_uss_user_sport_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_home_team');
            $table->dropIndex('idx_matches_away_team');
            $table->dropIndex('idx_matches_winner');
            $table->dropIndex('idx_matches_tournament_type');
            $table->dropIndex('idx_matches_group');
            $table->dropIndex('idx_matches_status');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropIndex('idx_team_members_team');
        });

        Schema::table('mini_team_members', function (Blueprint $table) {
            $table->dropIndex('idx_mini_team_members_team');
        });

        Schema::table('user_sport_scores', function (Blueprint $table) {
            $table->dropIndex('idx_uss_user_sport_score');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains('Key_name', $index);
    }
};
