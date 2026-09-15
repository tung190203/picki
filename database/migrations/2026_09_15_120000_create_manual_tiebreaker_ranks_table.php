<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng lưu thứ hạng thủ công (manual tiebreaker) do BTC chỉ định khi:
 * - 2 đội bằng điểm, bằng hiệu số, bằng bàn thắng bại, đối đầu vòng tròn cycle
 *   (3 đội đồng hạng: A thắng B, B thắng C, C thắng A) → H2H không phân định được.
 *
 * Mục đích:
 * - Lưu lại quyết định của BTC (kéo-thả hoặc bốc thăm) cho từng cụm đồng hạng.
 * - GroupStandingRanker::rank() và CrossGroupComparisonService::rankCandidates()
 *   sẽ query bảng này để áp dụng manual_rank ASC trong các cụm đồng hạng.
 * - Khi BTC reset → xóa row → hệ thống fallback về team_id ASC (deterministic).
 *
 * Schema:
 *  - tournament_type_id: phân biệt giải
 *  - group_id: phân biệt bảng (để có thể đồng thời nhiều bảng đồng hạng)
 *  - team_id: đội được gán thứ hạng thủ công
 *  - manual_rank: 1-based rank do BTC gán trong CỤM đồng hạng
 *  - candidate_type (nullable): 'runner_up' | 'third_place' — dùng cho cross-group
 *    để phân biệt cụm Nhì vs cụm Ba.
 *  - set_by_user_id + set_at: audit
 *
 * Indexes:
 *  - unique (type + group + team): 1 đội chỉ có 1 manual_rank trong 1 group
 *  - idx (type + group + candidate_type + manual_rank): tra cứu nhanh theo cụm
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_tiebreaker_ranks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tournament_type_id');
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('team_id');

            // 1-based rank do BTC gán trong cụm đồng hạng (1 = cao nhất)
            $table->unsignedTinyInteger('manual_rank');

            // Phân biệt cụm Nhì vs cụm Ba khi áp dụng cho cross-group
            // null = mặc định (intra-group), 'runner_up' | 'third_place'
            $table->string('candidate_type', 32)->nullable();

            $table->unsignedBigInteger('set_by_user_id')->nullable();
            $table->timestamp('set_at')->nullable();

            $table->timestamps();

            // Mỗi (group, team) chỉ có 1 manual rank
            $table->unique(
                ['tournament_type_id', 'group_id', 'candidate_type', 'team_id'],
                'mtie_unique'
            );

            // Tra cứu nhanh: 1 group + candidate_type → ordered by manual_rank
            $table->index(
                ['tournament_type_id', 'group_id', 'candidate_type'],
                'mtie_lookup'
            );

            $table->foreign('tournament_type_id', 'mtie_tt_fk')
                ->references('id')->on('tournament_types')
                ->cascadeOnDelete();
            $table->foreign('group_id', 'mtie_g_fk')
                ->references('id')->on('groups')
                ->cascadeOnDelete();
            $table->foreign('team_id', 'mtie_t_fk')
                ->references('id')->on('teams')
                ->cascadeOnDelete();
            $table->foreign('set_by_user_id', 'mtie_usr_fk')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_tiebreaker_ranks');
    }
};
