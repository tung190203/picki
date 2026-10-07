<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * club_competition_locations — pivot CLB ↔ competition_location
     * đại diện cho "sân nhà" của CLB (1 CLB có thể có nhiều sân).
     */
    public function up(): void
    {
        Schema::create('club_competition_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('competition_location_id')->constrained('competition_locations')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)
                ->comment('Thứ tự ưu tiên hiển thị, số nhỏ lên đầu');
            $table->decimal('distance_km', 6, 2)->nullable()
                ->comment('Khoảng cách từ CLB tới sân (km) — nhập tay');
            $table->unsignedInteger('events_hosted_count')->default(0)
                ->comment('Số kèo/giải CLB đã tổ chức tại sân này — admin tự cập nhật');
            $table->timestamps();

            $table->unique(['club_id', 'competition_location_id'], 'club_comp_loc_unique');
            $table->index(['club_id', 'position'], 'club_comp_loc_club_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_competition_locations');
    }
};
