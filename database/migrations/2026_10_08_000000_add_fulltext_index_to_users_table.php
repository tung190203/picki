<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Thêm FULLTEXT index cho users.full_name và users.email để tối ưu keyword search.
     */
    public function up(): void
    {
        // Chỉ chạy trên MySQL (FULLTEXT chỉ supported bởi MySQL/MariaDB)
        if (DB::getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->fullText(['full_name', 'email'], 'users_fulltext_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropFullText('users_fulltext_index');
            });
        }
    }
};
