<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ========================
        // MATCHES: index tổng hợp (composite) cho query thường dùng nhất
        // covers: WHERE winner_id = ? AND status = 'completed' + join team_members
        Schema::table('matches', function (Blueprint $table) {
            if (! $this->indexExists('matches', 'idx_matches_winner_status')) {
                $table->index(['winner_id', 'status'], 'idx_matches_winner_status');
            }
        });

        // ========================
        // TEAM_MEMBERS: index trên user_id (cho lookup user -> teams)
        Schema::table('team_members', function (Blueprint $table) {
            if (! $this->indexExists('team_members', 'idx_team_members_user')) {
                $table->index('user_id', 'idx_team_members_user');
            }
        });

        // ========================
        // MINI_TEAM_MEMBERS: index trên user_id (cho lookup user -> mini_teams)
        Schema::table('mini_team_members', function (Blueprint $table) {
            if (! $this->indexExists('mini_team_members', 'idx_mini_team_members_user')) {
                $table->index('user_id', 'idx_mini_team_members_user');
            }
        });

        // ========================
        // MINI_MATCHES: index tổng hợp cho user win lookup
        Schema::table('mini_matches', function (Blueprint $table) {
            if (! $this->indexExists('mini_matches', 'idx_mini_matches_team_win_status')) {
                $table->index(['team_win_id', 'status'], 'idx_mini_matches_team_win_status');
            }
            if (! $this->indexExists('mini_matches', 'idx_mini_matches_mini_tournament')) {
                $table->index('mini_tournament_id', 'idx_mini_matches_mini_tournament');
            }
            if (! $this->indexExists('mini_matches', 'idx_mini_matches_status')) {
                $table->index('status', 'idx_mini_matches_status');
            }
        });

        // ========================
        // MINI_PARTICIPANTS: index trên user_id
        Schema::table('mini_participants', function (Blueprint $table) {
            if (! $this->indexExists('mini_participants', 'idx_mini_participants_user')) {
                $table->index('user_id', 'idx_mini_participants_user');
            }
            if (! $this->indexExists('mini_participants', 'idx_mini_participants_mini_tournament_user')) {
                $table->index(['mini_tournament_id', 'user_id', 'is_confirmed'], 'idx_mini_participants_mini_tournament_user');
            }
        });

        // ========================
        // MINI_TOURNAMENTS: index trên sport_id + status
        Schema::table('mini_tournaments', function (Blueprint $table) {
            if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_sport_status')) {
                $table->index(['sport_id', 'status'], 'idx_mini_tournaments_sport_status');
            }
            if (! $this->indexExists('mini_tournaments', 'idx_mini_tournaments_sport_start')) {
                $table->index(['sport_id', 'status', 'start_time'], 'idx_mini_tournaments_sport_start');
            }
        });

        // ========================
        // MINI_TOURNAMENT_STAFF: index trên user_id
        Schema::table('mini_tournament_staff', function (Blueprint $table) {
            if (! $this->indexExists('mini_tournament_staff', 'idx_mini_tournament_staff_user')) {
                $table->index('user_id', 'idx_mini_tournament_staff_user');
            }
            if (! $this->indexExists('mini_tournament_staff', 'idx_mini_tournament_staff_user_role')) {
                $table->index(['mini_tournament_id', 'user_id', 'role'], 'idx_mini_tournament_staff_user_role');
            }
        });

        // ========================
        // MATCH_HISTORIES: index trên user_id + quick_match_id (unique đã có, thêm user_id index)
        Schema::table('match_histories', function (Blueprint $table) {
            if (! $this->indexExists('match_histories', 'idx_match_histories_user')) {
                $table->index('user_id', 'idx_match_histories_user');
            }
        });

        // ========================
        // QUICK_MATCHES: index trên status + winner
        Schema::table('quick_matches', function (Blueprint $table) {
            if (! $this->indexExists('quick_matches', 'idx_quick_matches_status_winner')) {
                $table->index(['status', 'winner'], 'idx_quick_matches_status_winner');
            }
        });

        // ========================
        // TOURNAMENTS: index trên sport_id + status
        Schema::table('tournaments', function (Blueprint $table) {
            if (! $this->indexExists('tournaments', 'idx_tournaments_sport_status')) {
                $table->index(['sport_id', 'status'], 'idx_tournaments_sport_status');
            }
            if (! $this->indexExists('tournaments', 'idx_tournaments_sport_start')) {
                $table->index(['sport_id', 'status', 'start_date'], 'idx_tournaments_sport_start');
            }
        });

        // ========================
        // TOURNAMENT_STAFF: index trên user_id
        Schema::table('tournament_staff', function (Blueprint $table) {
            if (! $this->indexExists('tournament_staff', 'idx_tournament_staff_user')) {
                $table->index('user_id', 'idx_tournament_staff_user');
            }
        });

        // ========================
        // USER_SPORT: index trên user_id + sport_id
        Schema::table('user_sport', function (Blueprint $table) {
            if (! $this->indexExists('user_sport', 'idx_user_sport_user_sport')) {
                $table->index(['user_id', 'sport_id'], 'idx_user_sport_user_sport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('idx_matches_winner_status');
        });
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropIndex('idx_team_members_user');
        });
        Schema::table('mini_team_members', function (Blueprint $table) {
            $table->dropIndex('idx_mini_team_members_user');
        });
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->dropIndex('idx_mini_matches_team_win_status');
            $table->dropIndex('idx_mini_matches_mini_tournament');
            $table->dropIndex('idx_mini_matches_status');
        });
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropIndex('idx_mini_participants_user');
            $table->dropIndex('idx_mini_participants_mini_tournament_user');
        });
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_mini_tournaments_sport_status');
            $table->dropIndex('idx_mini_tournaments_sport_start');
        });
        Schema::table('mini_tournament_staff', function (Blueprint $table) {
            $table->dropIndex('idx_mini_tournament_staff_user');
            $table->dropIndex('idx_mini_tournament_staff_user_role');
        });
        Schema::table('match_histories', function (Blueprint $table) {
            $table->dropIndex('idx_match_histories_user');
        });
        Schema::table('quick_matches', function (Blueprint $table) {
            $table->dropIndex('idx_quick_matches_status_winner');
        });
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropIndex('idx_tournaments_sport_status');
            $table->dropIndex('idx_tournaments_sport_start');
        });
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->dropIndex('idx_tournament_staff_user');
        });
        Schema::table('user_sport', function (Blueprint $table) {
            $table->dropIndex('idx_user_sport_user_sport');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
