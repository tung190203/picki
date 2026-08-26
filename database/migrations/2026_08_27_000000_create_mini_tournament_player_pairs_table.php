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
        Schema::create('mini_tournament_player_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mini_tournament_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('player1_id'); // user_id hoặc mini_participant_id cho guest
            $table->unsignedBigInteger('player2_id');
            $table->boolean('player1_is_guest')->default(false);
            $table->boolean('player2_is_guest')->default(false);
            $table->string('pair_color', 20)->nullable(); // cyan, orange, teal, purple, pink, amber
            $table->timestamps();

            // Indexes for quick lookups (short names to avoid MySQL 64-char limit)
            $table->index(['mini_tournament_id', 'player1_id', 'player1_is_guest'], 'mtpp_p1_idx');
            $table->index(['mini_tournament_id', 'player2_id', 'player2_is_guest'], 'mtpp_p2_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mini_tournament_player_pairs');
    }
};
