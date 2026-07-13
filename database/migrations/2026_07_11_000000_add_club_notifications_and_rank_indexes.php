<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Indexes bổ sung để tối ưu API:
     * - GET /api/clubs/{id}/notifications
     * - GET /api/clubs/{id}  (calculateClubRank fallback)
     */
    public function up(): void
    {
        // club_notification_recipients: composite cho whereHas('recipients', user_id)
        // và subquery is_read_by_me ở getNotifications
        Schema::table('club_notification_recipients', function (Blueprint $table) {
            if (! $this->indexExists('club_notification_recipients', 'idx_recipients_cn_user')) {
                $table->index(['club_notification_id', 'user_id'], 'idx_recipients_cn_user');
            }
        });

        // club_notifications: paginate theo (club_id, status, created_at) + order by is_pinned
        Schema::table('club_notifications', function (Blueprint $table) {
            if (! $this->indexExists('club_notifications', 'idx_cn_club_status_pinned_created')) {
                $table->index(
                    ['club_id', 'status', 'is_pinned', 'created_at'],
                    'idx_cn_club_status_pinned_created'
                );
            }
        });

        // vndupr_history: ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at)
        Schema::table('vndupr_history', function (Blueprint $table) {
            if (! $this->indexExists('vndupr_history', 'idx_vndupr_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_vndupr_user_created');
            }
        });

        // clubs: filter is_public + status ở nhiều query paginate
        Schema::table('clubs', function (Blueprint $table) {
            if (! $this->indexExists('clubs', 'idx_clubs_public_status')) {
                $table->index(['is_public', 'status'], 'idx_clubs_public_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('club_notification_recipients', function (Blueprint $table) {
            $table->dropIndex('idx_recipients_cn_user');
        });
        Schema::table('club_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_cn_club_status_pinned_created');
        });
        Schema::table('vndupr_history', function (Blueprint $table) {
            $table->dropIndex('idx_vndupr_user_created');
        });
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropIndex('idx_clubs_public_status');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->contains('Key_name', $index);
    }
};
