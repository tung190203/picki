<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mini_tournament_draft_reminders', function (Blueprint $table) {
            $table->id();
            // CASCADE on mini_tournament_id: if a tournament is hard-deleted,
            // its reminder log goes with it. NOTE: this model uses SoftDeletes,
            // so soft-delete on MiniTournament does NOT trigger cascade here.
            $table->foreignId('mini_tournament_id')->constrained()->cascadeOnDelete();
            // RESTRICT (not cascade) on user_id: preserving reminder history
            // when a user account is deleted prevents re-spamming a recreated
            // account with the same id, and keeps an audit trail.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['mini_tournament_id', 'user_id'], 'draft_reminders_tournament_user_unique');
            $table->index(['sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mini_tournament_draft_reminders');
    }
};
