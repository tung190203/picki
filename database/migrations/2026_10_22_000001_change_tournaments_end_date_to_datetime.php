<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `tournaments.end_date` được khai báo là DATE nên MySQL cắt mất phần giờ.
     * Mọi so sánh `end_date < now()` vì thế chỉ đúng sau NỮA ĐÊM ngày kết thúc:
     * giải 1340 (start 2026-10-21 19:00 + 1440 phút = 2026-10-22 19:00) bị
     * `tournaments:auto-close` đóng lúc 12:50 ngày 22 — sớm hơn thực tế 6 tiếng.
     *
     * Sửa: đổi cột sang DATETIME và backfill lại từ start_date + duration
     * (cùng công thức với TournamentService::calculateEndDate) cho các dòng
     * bị cắt giờ trước đây.
     */
    public function up(): void
    {
        if (Schema::getColumnType('tournaments', 'end_date') === 'date') {
            DB::statement('ALTER TABLE tournaments MODIFY COLUMN end_date DATETIME NULL');
        }

        DB::statement(
            'UPDATE tournaments
             SET end_date = DATE_ADD(start_date, INTERVAL duration MINUTE)
             WHERE start_date IS NOT NULL
               AND duration IS NOT NULL
               AND (end_date IS NULL OR end_date < DATE_ADD(start_date, INTERVAL duration MINUTE))'
        );
    }

    public function down(): void
    {
        DB::statement(
            'UPDATE tournaments
             SET end_date = DATE(end_date)
             WHERE end_date IS NOT NULL'
        );

        DB::statement('ALTER TABLE tournaments MODIFY COLUMN end_date DATE NULL');
    }
};