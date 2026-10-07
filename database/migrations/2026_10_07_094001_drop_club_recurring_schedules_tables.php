<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop bảng lịch sinh hoạt định kỳ theo cấu trúc cũ.
     * club_recurring_schedule_locations (pivot) phải drop TRƯỚC
     * vì nó có FK tham chiếu club_recurring_schedules.
     */
    public function up(): void
    {
        Schema::dropIfExists('club_recurring_schedule_locations');
        Schema::dropIfExists('club_recurring_schedules');
    }

    public function down(): void
    {
        // Không rollback — cấu trúc mới là text fields trên clubs.
        $this->addCommands();
    }
};
