<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 — Rally Scoring: bảng lưu trữ stage kết quả cho các trận rally.
     *
     * Mỗi trận rally có 2..5 stage. Mỗi stage lưu:
     * - cumulative_score_a/b: điểm lũy kế cuối stage
     * - stage_score_a/b: điểm riêng của stage (= delta giữa 2 cumulative liên tiếp)
     * - target_point: snapshot của stage_points[stage_no-1]
     * - status: pending|completed
     * - winner_team_id: đội thắng stage (snapshot khi chạm target_point)
     *
     * Side-out scoring không ghi vào bảng này.
     */
    public function up(): void
    {
        Schema::create('match_stages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('match_id');
            $table->unsignedTinyInteger('stage_no'); // 1..N (max 5 theo MAX_STAGES)

            $table->unsignedSmallInteger('cumulative_score_a')->default(0);
            $table->unsignedSmallInteger('cumulative_score_b')->default(0);
            $table->unsignedSmallInteger('stage_score_a')->default(0);
            $table->unsignedSmallInteger('stage_score_b')->default(0);

            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('winner_team_id')->nullable();
            $table->unsignedSmallInteger('target_point')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['match_id', 'stage_no']);
            $table->index(['match_id', 'status']);

            $table->foreign('match_id')
                ->references('id')->on('matches')
                ->cascadeOnDelete();
            $table->foreign('winner_team_id')
                ->references('id')->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_stages');
    }
};
