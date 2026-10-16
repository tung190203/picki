<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 4 — Rally Scoring: bổ sung match_stage_id vào vndupr_history.
     *
     * Vấn đề:
     * - UNIQUE cũ (user_id, match_id) cản rally vì 1 user có thể có nhiều row (mỗi stage).
     *
     * Giải pháp:
     * - Thêm match_stage_id NULL được.
     * - Replace UNIQUE cũ thành (user_id, match_id, match_stage_id).
     *   MySQL cho phép nhiều NULL trong unique index → side-out (stage NULL) vẫn chỉ 1 row,
     *   rally (stage 1, 2, 3) phân biệt được.
     *
     * An toàn cho dữ liệu cũ:
     * - Row cũ match_stage_id = NULL, unique mới vẫn hợp lệ.
     * - FK nullOnDelete: nếu stage bị xóa → row cũ vẫn còn (chỉ mất tham chiếu).
     */
    public function up(): void
    {
        Schema::table('vndupr_history', function (Blueprint $table) {
            // 1. Drop UNIQUE cũ
            try {
                $table->dropUnique('vndupr_history_user_id_match_id_unique');
            } catch (\Throwable $e) {
                // index có thể không tồn tại tùy môi trường; bỏ qua
            }

            // 2. Thêm cột match_stage_id nullable
            $table->unsignedBigInteger('match_stage_id')->nullable()->after('match_id');

            // 3. Thêm UNIQUE mới
            $table->unique(
                ['user_id', 'match_id', 'match_stage_id'],
                'vndupr_history_user_match_stage_unique',
            );
        });

        // FK được add sau (riêng) để tránh vấn đề với transactional DDL
        // trên một số MySQL versions.
        Schema::table('vndupr_history', function (Blueprint $table) {
            $table->foreign('match_stage_id')
                ->references('id')->on('match_stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vndupr_history', function (Blueprint $table) {
            // Bỏ FK trước
            try {
                $table->dropForeign(['match_stage_id']);
            } catch (\Throwable $e) {
            }

            $table->dropUnique('vndupr_history_user_match_stage_unique');
            $table->dropColumn('match_stage_id');
            $table->unique(['user_id', 'match_id'], 'vndupr_history_user_id_match_id_unique');
        });
    }
};
