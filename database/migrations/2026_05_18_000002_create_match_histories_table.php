<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('quick_match_id');
            $table->string('team_side', 10); // 'team_a' or 'team_b'
            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('quick_match_id')->references('id')->on('quick_matches')->onDelete('cascade');
            $table->unique(['user_id', 'quick_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_histories');
    }
};
