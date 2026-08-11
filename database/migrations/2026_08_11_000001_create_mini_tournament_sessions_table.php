<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mini_tournament_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mini_tournament_id')->constrained()->cascadeOnDelete();
            // [[user_id,...], ...] sorted ascending per entry, representing tried 4-player combos
            $table->json('tried_suggestions')->nullable();
            // Current position in the rotation cycle. 0 means "first time".
            $table->unsignedInteger('rotation_index')->default(0);
            // Last time a suggestion was produced (initial or regenerated).
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique('mini_tournament_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mini_tournament_sessions');
    }
};
