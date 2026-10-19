<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('club_id')->nullable()->constrained('clubs')->nullOnDelete();
            // club_id dùng cho summary theo CLB (chỉ fill khi event là mini_tournament
            // của CLB đó, hoặc tournament do CLB đó host). Khi user thuộc nhiều CLB
            // và đạt cup ở giải ngoài, mỗi CLB sẽ tạo 1 row riêng.

            // Phân loại: 'mini_tournament' (star) hoặc 'tournament' (cup)
            $table->string('event_type', 20);
            $table->unsignedBigInteger('event_id');

            // Snapshot để hiển thị không cần join
            $table->string('event_name');
            $table->timestamp('event_date')->nullable();
            $table->string('event_status', 20)->nullable();
            $table->boolean('is_club_hosted')->default(false);

            $table->unsignedTinyInteger('rank'); // 1, 2, 3
            $table->string('medal', 10); // gold | silver | bronze
            $table->unsignedTinyInteger('points'); // 3/2/1 cho star, 1 cho cup
            $table->boolean('is_star')->default(false);
            $table->boolean('is_cup')->default(false);
            // Snapshot is_guest của participant/member tại lúc ghi — dùng cho
            // BXH (ẩn guest khỏi ranking) vì bảng không còn join runtime.
            $table->boolean('is_guest_snapshot')->default(false);

            $table->json('partner_names')->nullable();

            $table->timestamps();

            // Idempotent: 1 user × 1 event × 1 club chỉ tạo 1 row
            $table->unique(
                ['user_id', 'club_id', 'event_type', 'event_id'],
                'user_achievements_unique'
            );

            // Query theo user, sort theo ngày
            $table->index(['user_id', 'event_date']);

            // Query theo event để biết ai đạt
            $table->index(['event_type', 'event_id']);

            // Query theo club (BXH CLB)
            $table->index(['club_id', 'is_cup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
