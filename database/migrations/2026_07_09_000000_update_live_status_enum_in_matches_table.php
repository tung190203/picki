<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add 'finish' to enum (temporarily allow both 'finished' and 'finish')
        DB::statement("ALTER TABLE matches MODIFY COLUMN live_status ENUM('waiting', 'playing', 'timeout', 'between_sets', 'finished', 'finish', 'cancelled') DEFAULT 'waiting'");

        // Step 2: Update all 'finished' values to 'finish'
        DB::table('matches')->where('live_status', 'finished')->update(['live_status' => 'finish']);

        // Step 3: Remove 'finished' from enum
        DB::statement("ALTER TABLE matches MODIFY COLUMN live_status ENUM('waiting', 'playing', 'timeout', 'between_sets', 'finish', 'cancelled') DEFAULT 'waiting'");
    }

    public function down(): void
    {
        // Step 1: Add 'finished' back to enum
        DB::statement("ALTER TABLE matches MODIFY COLUMN live_status ENUM('waiting', 'playing', 'timeout', 'between_sets', 'finish', 'finished', 'cancelled') DEFAULT 'waiting'");

        // Step 2: Update 'finish' values back to 'finished'
        DB::table('matches')->where('live_status', 'finish')->update(['live_status' => 'finished']);

        // Step 3: Remove 'finish' from enum
        DB::statement("ALTER TABLE matches MODIFY COLUMN live_status ENUM('waiting', 'playing', 'timeout', 'between_sets', 'finished', 'cancelled') DEFAULT 'waiting'");
    }
};
