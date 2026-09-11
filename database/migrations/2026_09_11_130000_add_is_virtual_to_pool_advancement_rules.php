<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung cờ cho "bảng ảo" — các slot do cross-group comparison fill vào.
 *
 * Trước đây, các slot Nhì tốt nhất chưa được tạo PoolAdvancementRule (vì
 * không có group_id thật). Điều này khiến `applyPoolAdvancement()` chỉ dựa
 * vào việc quét "match round 2 trống không có rule" → dễ sót và không
 * idempotent. Fix bằng cách tạo 1 row rule với:
 *   - group_id = NULL
 *   - is_virtual = true
 *   - virtual_index = 1..N (để xác định Nhì #1, #2, #3)
 *   - rank = 2 (giữ ngữ nghĩa Nhì cho UI)
 *   - next_match_id + next_position: trỏ tới slot trống tương ứng
 *
 * applyPoolAdvancement() sẽ thấy group_id IS NULL && is_virtual=true → resolve
 * qua CrossGroupComparisonService::resolveVirtualGroupAdvancing().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pool_advancement_rules', function (Blueprint $table) {
            $table->boolean('is_virtual')->default(false)->after('group_id');
            // Index 1..N khi is_virtual=true: phân biệt Nhì #1, Nhì #2, ...
            $table->unsignedTinyInteger('virtual_index')->nullable()->after('is_virtual');

            // Hỗ trợ truy vấn "resolve các rule ảo còn pending"
            $table->index(['tournament_type_id', 'is_virtual']);
        });
    }

    public function down(): void
    {
        Schema::table('pool_advancement_rules', function (Blueprint $table) {
            $table->dropIndex(['tournament_type_id', 'is_virtual']);
            $table->dropColumn(['is_virtual', 'virtual_index']);
        });
    }
};
