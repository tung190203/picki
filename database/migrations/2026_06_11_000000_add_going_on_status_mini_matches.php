<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `mini_matches` MODIFY COLUMN `status` ENUM('pending','going_on','waiting_confirm','completed','disputed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `mini_matches` MODIFY COLUMN `status` ENUM('pending','completed','disputed') NOT NULL DEFAULT 'pending'");
    }
};
