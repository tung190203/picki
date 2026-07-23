<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('rank');
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sport_id', 'recorded_at']);
            $table->index(['sport_id', 'rank']);
            $table->index(['user_id', 'sport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_ranks');
    }
};
