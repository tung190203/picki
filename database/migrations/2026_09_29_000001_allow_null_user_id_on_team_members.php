<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cho phép user_id NULL trên team_members để support guest participant
     * (tham chiếu qua participant_id — không có User).
     */
    public function up(): void
    {
        // Drop FK cũ
        Schema::table('team_members', function ($table) {
            $table->dropForeign(['user_id']);
        });

        // Đổi column thành nullable (raw SQL — tránh dependency doctrine/dbal)
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE team_members MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite cho phép nullable mặc định nếu column chưa có NOT NULL constraint
            // (Schema ban đầu không khai báo NOT NULL → đã nullable rồi; vẫn an toàn nếu re-run)
        }

        // Recreate FK với onDelete set null
        Schema::table('team_members', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function ($table) {
            $table->dropForeign(['user_id']);
        });
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE team_members MODIFY user_id BIGINT UNSIGNED NOT NULL');
        }
        Schema::table('team_members', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};