<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->json('rules');
            $table->json('team_a');
            $table->json('team_b');
            $table->json('score')->nullable();
            $table->string('status', 20)->default('playing');
            $table->timestamps();

            $table->index(['creator_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_matches');
    }
};
