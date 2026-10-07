<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * club_recurring_schedules — lịch sinh hoạt định kỳ của CLB.
     * Mỗi dòng = 1 khung giờ trong tuần (thứ + start_time + end_time + ghi chú).
     * Gắn với CLB, không gắn sân (cùng 1 lịch áp dụng cho mọi sân nhà).
     */
    public function up(): void
    {
        Schema::create('club_recurring_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')
                ->comment('0 = Chủ nhật, 1 = Thứ 2, ..., 6 = Thứ 7');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('note')->nullable()
                ->comment('Ghi chú (vd: Sân chính, Tập cơ bản)');
            $table->unsignedInteger('position')->default(0)
                ->comment('Thứ tự hiển thị phụ trong cùng thứ');
            $table->timestamps();

            $table->index(['club_id', 'day_of_week', 'position'], 'club_sched_club_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_recurring_schedules');
    }
};
