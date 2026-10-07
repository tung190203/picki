<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->text('rules')->nullable()
                ->comment('Nội quy CLB — text tự do, BE/FE tự định dạng hiển thị');
            $table->text('recurring_schedule_text')->nullable()
                ->comment('Lịch sinh hoạt định kỳ — text tự do thay vì cấu trúc bảng riêng');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['rules', 'recurring_schedule_text']);
        });
    }
};
