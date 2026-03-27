<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Thêm cột created_by vào bảng mini_tournaments nếu chưa tồn tại.
     * Migration này an toàn để chạy nhiều lần.
     */
    public function up(): void
    {
        // Kiểm tra xem bảng có tồn tại không
        if (!Schema::hasTable('mini_tournaments')) {
            return;
        }

        // Kiểm tra xem cột created_by đã tồn tại chưa
        if (Schema::hasColumn('mini_tournaments', 'created_by')) {
            return;
        }

        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('sport_id');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('mini_tournaments')) {
            return;
        }

        if (!Schema::hasColumn('mini_tournaments', 'created_by')) {
            return;
        }

        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
