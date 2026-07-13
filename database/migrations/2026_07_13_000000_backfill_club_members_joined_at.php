<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: rows already 'joined' but missing joined_at fall back to created_at
        DB::statement("
            UPDATE club_members
            SET joined_at = created_at
            WHERE membership_status = 'joined'
              AND joined_at IS NULL
        ");
    }

    public function down(): void
    {
        // No-op: backfill is non-destructive
    }
};