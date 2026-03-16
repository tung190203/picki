<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            // Lưu fee_per_person cuối cùng khi kèo kết thúc (để tránh thay đổi)
            $table->unsignedInteger('final_fee_per_person')->nullable()->after('fee_amount')
                ->comment('Fee per person cuối cùng khi kèo kết thúc (lock để tránh thay đổi)');
            
            // Đánh dấu đã tạo khoản thu tự động
            $table->boolean('auto_payment_created')->default(false)->after('final_fee_per_person')
                ->comment('Đã tạo khoản thu tự động khi kèo kết thúc');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn(['final_fee_per_person', 'auto_payment_created']);
        });
    }
};
