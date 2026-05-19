<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Đổi team_a và team_b từ enum sang json
        DB::statement("ALTER TABLE quick_matches MODIFY COLUMN team_a JSON NOT NULL");
        DB::statement("ALTER TABLE quick_matches MODIFY COLUMN team_b JSON NOT NULL");
    }

    public function down(): void
    {
        // rollback về enum (cần xđịnh lại giá trị enum mặc định)
        DB::statement("ALTER TABLE quick_matches MODIFY COLUMN team_a ENUM('[]') NOT NULL");
        DB::statement("ALTER TABLE quick_matches MODIFY COLUMN team_b ENUM('[]') NOT NULL");
    }
};
