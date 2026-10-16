<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 — Rally Scoring: thành viên thực tế thi đấu trong từng stage.
     *
     * Lưu user thật (không có gender constraint, không có cột gender).
     * Một stage có thể có nhiều thành viên mỗi đội. Service tự điền mặc định
     * từ team_members nếu client không gửi.
     */
    public function up(): void
    {
        Schema::create('match_stage_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('match_stage_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 16)->default('player');

            $table->timestamps();

            $table->unique(['match_stage_id', 'user_id']);
            $table->index(['team_id']);

            $table->foreign('match_stage_id')
                ->references('id')->on('match_stages')
                ->cascadeOnDelete();
            $table->foreign('team_id')
                ->references('id')->on('teams')
                ->cascadeOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_stage_members');
    }
};
