<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change user_id FK on mini_tournament_draft_reminders from CASCADE to
     * RESTRICT, so deleting a user account does not wipe the reminder log
     * (which would cause the draft reminder job to re-spam a recreated
     * account with the same id).
     */
    public function up(): void
    {
        $foreignKey = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'mini_tournament_draft_reminders'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME = 'users'
             LIMIT 1"
        );

        if ($foreignKey && isset($foreignKey->CONSTRAINT_NAME)) {
            $constraint = $foreignKey->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE mini_tournament_draft_reminders DROP FOREIGN KEY `{$constraint}`");
        }

        Schema::table('mini_tournament_draft_reminders', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $foreignKey = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'mini_tournament_draft_reminders'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME = 'users'
             LIMIT 1"
        );

        if ($foreignKey && isset($foreignKey->CONSTRAINT_NAME)) {
            $constraint = $foreignKey->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE mini_tournament_draft_reminders DROP FOREIGN KEY `{$constraint}`");
        }

        Schema::table('mini_tournament_draft_reminders', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }
};
