<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            if (!Schema::hasColumn('matches', 'court_id')) {
                $table->foreignId('court_id')
                    ->nullable()
                    ->after('court')
                    ->constrained('tournament_courts')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('matches', 'priority_at')) {
                $table->timestamp('priority_at')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('matches', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }

            $table->index(['court_id', 'status', 'priority_at', 'scheduled_at'], 'matches_court_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_court_queue_idx');
            if (Schema::hasColumn('matches', 'court_id')) {
                $table->dropConstrainedForeignId('court_id');
            }
            if (Schema::hasColumn('matches', 'priority_at')) {
                $table->dropColumn('priority_at');
            }
            if (Schema::hasColumn('matches', 'finished_at')) {
                $table->dropColumn('finished_at');
            }
        });
    }
};
