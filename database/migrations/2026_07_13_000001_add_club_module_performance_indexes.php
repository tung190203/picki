<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add performance indexes for Club module.
     *
     * Run AFTER Phase 0 profiling confirms DB as bottleneck.
     * Run EXPLAIN ANALYZE before/after to verify index usage.
     * Safe to re-run — checks indexExists before creating.
     */
    public function up(): void
    {
        // club_members: covers attachMembershipStatus, attachUnreadNotificationCount (cm subquery),
        // getMemberStatistics, getLeaderboard user lookup
        // Note: idx_club_members_user_club_status already exists in 2026_06_05 migration
        // This migration adds the remaining 3 indexes
        Schema::table('club_members', function (Blueprint $table) {
            if (! $this->indexExists('club_members', 'idx_club_members_club_user')) {
                $table->index(
                    ['club_id', 'user_id'],
                    'idx_club_members_club_user'
                );
            }
        });

        // club_notifications: covers attachUnreadNotificationCount recipient join
        Schema::table('club_notifications', function (Blueprint $table) {
            if (! $this->indexExists('club_notifications', 'idx_club_notifications_club_status_sent')) {
                $table->index(
                    ['club_id', 'status', 'sent_at'],
                    'idx_club_notifications_club_status_sent'
                );
            }
        });

        // club_notification_recipients: covers attachUnreadNotificationCount recipient join
        Schema::table('club_notification_recipients', function (Blueprint $table) {
            if (! $this->indexExists('club_notification_recipients', 'idx_cnr_user_read_notification')) {
                $table->index(
                    ['user_id', 'is_read', 'club_notification_id'],
                    'idx_cnr_user_read_notification'
                );
            }
        });

        // club_activities: covers recurring series collapse queries
        Schema::table('club_activities', function (Blueprint $table) {
            if (! $this->indexExists('club_activities', 'idx_club_activities_series_status_start')) {
                $table->index(
                    ['club_id', 'recurrence_series_id', 'status', 'start_time'],
                    'idx_club_activities_series_status_start'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            if ($this->indexExists('club_members', 'idx_club_members_club_user')) {
                $table->dropIndex('idx_club_members_club_user');
            }
        });

        Schema::table('club_notifications', function (Blueprint $table) {
            if ($this->indexExists('club_notifications', 'idx_club_notifications_club_status_sent')) {
                $table->dropIndex('idx_club_notifications_club_status_sent');
            }
        });

        Schema::table('club_notification_recipients', function (Blueprint $table) {
            if ($this->indexExists('club_notification_recipients', 'idx_cnr_user_read_notification')) {
                $table->dropIndex('idx_cnr_user_read_notification');
            }
        });

        Schema::table('club_activities', function (Blueprint $table) {
            if ($this->indexExists('club_activities', 'idx_club_activities_series_status_start')) {
                $table->dropIndex('idx_club_activities_series_status_start');
            }
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
