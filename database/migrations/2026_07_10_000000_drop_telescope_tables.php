<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop Telescope tables if they exist (safe - won't error if table doesn't exist)
        $tables = [
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ];

        // MySQL/SQLite compatible approach - check and drop
        // IMPORTANT: Must drop child tables before parent table due to foreign key constraints
        if (Schema::hasTable('telescope_entries_tags')) {
            Schema::dropIfExists('telescope_entries_tags');
        }

        if (Schema::hasTable('telescope_monitoring')) {
            Schema::dropIfExists('telescope_monitoring');
        }

        if (Schema::hasTable('telescope_entries')) {
            Schema::dropIfExists('telescope_entries');
        }
    }

    public function down(): void
    {
        // Note: Telescope tables cannot be recreated without the package
        // This is intentional - Telescope was never installed in this project
    }
};
