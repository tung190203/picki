<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add missing indexes for club notifications and activities performance.
     * These indexes optimize the queries used in:
     * - ClubNotificationService::getUnreadCount()
     * - ClubNotificationService::getNotifications()
     * - ClubActivityService::getActivities()
     */
    public function up(): void
    {
        // club_notifications: add composite index for status + sent_at queries
        // Used by getNotifications() and getUnreadCount() to filter by status = 'sent'
        Schema::table('club_notifications', function (Blueprint $table) {
            if (! $this->indexExists('club_notifications', 'idx_club_notifications_status_sent_at')) {
                $table->index(['status', 'sent_at'], 'idx_club_notifications_status_sent_at');
            }
        });

        // club_notification_recipients: add composite index for user_id + is_read
        // Used by getUnreadCount() to count unread notifications per user
        Schema::table('club_notification_recipients', function (Blueprint $table) {
            if (! $this->indexExists('club_notification_recipients', 'idx_recipients_user_read')) {
                $table->index(['user_id', 'is_read'], 'idx_recipients_user_read');
            }
        });

        // club_activities: add composite index for club + status + start_time
        // Used by getActivities() to filter activities by club, status, and date range
        Schema::table('club_activities', function (Blueprint $table) {
            if (! $this->indexExists('club_activities', 'idx_activities_club_status_start')) {
                $table->index(['club_id', 'status', 'start_time'], 'idx_activities_club_status_start');
            }
        });

        // club_activities: add index for recurrence_series_id
        // Used by getActivities() to handle recurring activities (collapse logic)
        Schema::table('club_activities', function (Blueprint $table) {
            if (! $this->indexExists('club_activities', 'idx_activities_series_status')) {
                $table->index(['recurrence_series_id', 'status'], 'idx_activities_series_status');
            }
        });

        // club_activity_participants: add composite index for activity + user lookups
        // Used by getActivities() to check user registration status
        Schema::table('club_activity_participants', function (Blueprint $table) {
            if (! $this->indexExists('club_activity_participants', 'idx_participants_activity_user')) {
                $table->index(['club_activity_id', 'user_id'], 'idx_participants_activity_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_club_notifications_status_sent_at');
        });

        Schema::table('club_notification_recipients', function (Blueprint $table) {
            $table->dropIndex('idx_recipients_user_read');
        });

        Schema::table('club_activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_club_status_start');
            $table->dropIndex('idx_activities_series_status');
        });

        Schema::table('club_activity_participants', function (Blueprint $table) {
            $table->dropIndex('idx_participants_activity_user');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains('Key_name', $index);
    }
};
