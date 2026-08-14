<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Flush column listing cache so Schema Builder sees the current DB state.
        Schema::getColumnListing('match_results');

        // Use raw statements throughout to avoid Schema Builder cache issues.
        // Drop the composite unique index that includes participant_id.
        try {
            DB::statement('ALTER TABLE `match_results` DROP INDEX `match_results_match_id_participant_id_unique`');
        } catch (\Exception $e) { /* already gone */ }

        // Drop FK constraint (MariaDB requires FK removed before dropping the column it references).
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('ALTER TABLE `match_results` DROP FOREIGN KEY `match_results_participant_id_foreign`');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            /* already gone */
        }

        // Drop the participant_id column.
        try {
            DB::statement('ALTER TABLE `match_results` DROP COLUMN `participant_id`');
        } catch (\Exception $e) { /* already gone */ }

        // Add new columns if they don't exist yet.
        try {
            DB::statement('ALTER TABLE `match_results` ADD COLUMN `team_id` BIGINT UNSIGNED NULL AFTER `match_id`');
        } catch (\Exception $e) { /* already exists */ }

        try {
            DB::statement('ALTER TABLE `match_results` ADD COLUMN `score` BIGINT UNSIGNED NULL AFTER `team_id`');
        } catch (\Exception $e) { /* already exists */ }

        try {
            DB::statement('ALTER TABLE `match_results` ADD COLUMN `set_number` BIGINT UNSIGNED NULL AFTER `score`');
        } catch (\Exception $e) { /* already exists */ }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->after('match_id');
            $table->dropColumn('team_id');
            $table->dropColumn('score');
            $table->dropColumn('set_number');
        });
    }
};
