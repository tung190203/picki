<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename column without doctrine/dbal by using raw SQL.
        // Assumes MySQL/MariaDB (project already uses FOREIGN_KEY_CHECKS elsewhere).
        if (Schema::hasTable('mini_matches') && Schema::hasColumn('mini_matches', 'name_of_match') && !Schema::hasColumn('mini_matches', 'name')) {
            DB::statement('ALTER TABLE `mini_matches` CHANGE `name_of_match` `name` VARCHAR(255) NULL');
        }

        Schema::table('mini_matches', function (Blueprint $table) {
            foreach (['round', 'yard_number', 'referee_id', 'scheduled_at'] as $col) {
                if (Schema::hasColumn('mini_matches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // Best-effort rollback. (Restored columns will be empty.)
        if (Schema::hasTable('mini_matches') && Schema::hasColumn('mini_matches', 'name') && !Schema::hasColumn('mini_matches', 'name_of_match')) {
            DB::statement('ALTER TABLE `mini_matches` CHANGE `name` `name_of_match` VARCHAR(255) NULL');
        }

        Schema::table('mini_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('mini_matches', 'round')) {
                $table->string('round')->nullable();
            }
            if (!Schema::hasColumn('mini_matches', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable();
            }
            if (!Schema::hasColumn('mini_matches', 'referee_id')) {
                $table->unsignedBigInteger('referee_id')->nullable();
            }
            if (!Schema::hasColumn('mini_matches', 'yard_number')) {
                $table->unsignedBigInteger('yard_number')->nullable();
            }
        });
    }
};

