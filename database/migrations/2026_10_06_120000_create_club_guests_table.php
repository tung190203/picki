<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * club_guests — bảng theo dõi user đã tham gia kèo/giải của CLB
     * nhưng chưa phải thành viên CLB.
     *
     * Upsert khi tournament/mini-tournament chuyển sang status kết thúc
     * (TournamentStatus::Finished / MiniTournament::STATUS_CLOSED).
     */
    public function up(): void
    {
        Schema::create('club_guests', function (Blueprint $table) {
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('play_count')->default(1)
                ->comment('Tổng số kèo/giải đã tham gia');
            $table->timestamp('first_played_at')->nullable();
            $table->timestamp('last_played_at')->nullable();
            $table->boolean('is_invited')->default(false)
                ->comment('Admin đã gửi lời mời tham gia CLB');
            $table->timestamps();

            $table->primary(['club_id', 'user_id']);
            $table->index(['club_id', 'last_played_at']);
            $table->index(['club_id', 'is_invited']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_guests');
    }
};
