<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * club_recurring_schedule_locations — pivot lịch sinh hoạt ↔ sân nhà.
     * Một lịch sinh hoạt (1 row ở club_recurring_schedules) có thể gắn với NHIỀU sân nhà.
     * Nếu không gắn sân nào → lịch đó áp dụng cho toàn bộ sân nhà của CLB.
     *
     * Cascade:
     *  - Xoá schedule → xoá hết pivot rows của nó
     *  - Xoá competition_location → cascade (tương tự club_competition_locations)
     *  - Xoá club → cascade từ schedule (đã cascade on club_id)
     */
    public function up(): void
    {
        Schema::create('club_recurring_schedule_locations', function (Blueprint $table) {
            $table->id();
            // FK name tự động = table_col_foreign > 64 chars trên MySQL.
            // Đặt tên ngắn tay cho FK để khỏi vượt giới hạn identifier.
            $table->foreignId('club_recurring_schedule_id')
                ->constrained('club_recurring_schedules', 'id', 'fk_crs_loc_sched')
                ->cascadeOnDelete();
            $table->foreignId('competition_location_id')
                ->constrained('competition_locations', 'id', 'fk_crs_loc_court')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['club_recurring_schedule_id', 'competition_location_id'],
                'crsl_unique'
            );
            $table->index('competition_location_id', 'crsl_court_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_recurring_schedule_locations');
    }
};
