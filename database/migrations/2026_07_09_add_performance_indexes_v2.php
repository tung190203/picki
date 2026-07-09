<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // mini_matches: composite index for status + team_win queries (leaderboard stats)
        Schema::table('mini_matches', function (Blueprint $table) {
            if (! $this->indexExists('mini_matches', 'idx_mini_matches_status_team_win')) {
                $table->index(['status', 'team_win_id'], 'idx_mini_matches_status_team_win');
            }
            if (! $this->indexExists('mini_matches', 'idx_mini_matches_mini_tournament_status')) {
                $table->index(['mini_tournament_id', 'status'], 'idx_mini_matches_mini_tournament_status');
            }
        });

        // mini_participants: composite index for mini_tournament user lookups
        Schema::table('mini_participants', function (Blueprint $table) {
            if (! $this->indexExists('mini_participants', 'idx_mini_participants_tournament_user')) {
                $table->index(['mini_tournament_id', 'user_id'], 'idx_mini_participants_tournament_user');
            }
            if (! $this->indexExists('mini_participants', 'idx_mini_participants_payment_status')) {
                $table->index(['mini_tournament_id', 'payment_status'], 'idx_mini_participants_payment_status');
            }
        });

        // mini_tournaments: composite index for status + sport queries
        Schema::table('mini_tournaments', function (Blueprint $table) {
            if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_status_sport')) {
                $table->index(['status', 'sport_id'], 'idx_mini_tournaments_status_sport');
            }
            if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_club_status')) {
                $table->index(['club_id', 'status'], 'idx_mini_tournaments_club_status');
            }
        });

        // tournaments: composite index for status + sport queries
        Schema::table('tournaments', function (Blueprint $table) {
            if (! $this->indexExists('tournaments', 'idx_tournaments_status_sport')) {
                $table->index(['status', 'sport_id'], 'idx_tournaments_status_sport');
            }
            if (! $this->indexExists('tournaments', 'idx_tournaments_club_status')) {
                $table->index(['club_id', 'status'], 'idx_tournaments_club_status');
            }
        });

        // follows: composite index for user follow lookups
        Schema::table('follows', function (Blueprint $table) {
            if (! $this->indexExists('follows', 'idx_follows_user_target')) {
                $table->index(['user_id', 'followable_type', 'followable_id'], 'idx_follows_user_target');
            }
        });

        // user_sport: composite index for sport stats queries
        Schema::table('user_sport', function (Blueprint $table) {
            if (! $this->indexExists('user_sport', 'idx_user_sport_sport_user')) {
                $table->index(['sport_id', 'user_id'], 'idx_user_sport_sport_user');
            }
        });

        // quick_matches: index for status queries
        Schema::table('quick_matches', function (Blueprint $table) {
            if (! $this->indexExists('quick_matches', 'idx_quick_matches_status_created')) {
                $table->index(['status', 'created_by'], 'idx_quick_matches_status_created');
            }
        });

        // audit_logs: index for action filtering
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! $this->indexExists('audit_logs', 'idx_audit_logs_action')) {
                $table->index('action', 'idx_audit_logs_action');
            }
        });

        // match_histories: composite index for user quick_match lookups
        Schema::table('match_histories', function (Blueprint $table) {
            if (! $this->indexExists('match_histories', 'idx_match_histories_user_quick_match')) {
                $table->index(['user_id', 'quick_match_id'], 'idx_match_histories_user_quick_match');
            }
        });

        // club_members: composite index for user membership status queries
        Schema::table('club_members', function (Blueprint $table) {
            if (! $this->indexExists('club_members', 'idx_club_members_user_status')) {
                $table->index(['user_id', 'membership_status', 'status'], 'idx_club_members_user_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->dropIndex('idx_mini_matches_status_team_win');
            $table->dropIndex('idx_mini_matches_mini_tournament_status');
        });

        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropIndex('idx_mini_participants_tournament_user');
            $table->dropIndex('idx_mini_participants_payment_status');
        });

        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_mini_tournaments_status_sport');
            $table->dropIndex('idx_mini_tournaments_club_status');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_tournaments_status_sport');
            $table->dropIndex('idx_tournaments_club_status');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('idx_follows_user_target');
        });

        Schema::table('user_sport', function (Blueprint $table) {
            $table->dropIndex('idx_user_sport_sport_user');
        });

        Schema::table('quick_matches', function (Blueprint $table) {
            $table->dropIndex('idx_quick_matches_status_created');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_action');
        });

        Schema::table('match_histories', function (Blueprint $table) {
            $table->dropIndex('idx_match_histories_user_quick_match');
        });

        Schema::table('club_members', function (Blueprint $table) {
            $table->dropIndex('idx_club_members_user_status');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains('Key_name', $index);
    }
};
