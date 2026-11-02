<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livescore_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_id')->index();
            // Null cho quick match (không thuộc giải nào).
            $table->unsignedBigInteger('tournament_id')->nullable()->index();
            $table->enum('theme', ['dark', 'light', 'photo'])->default('dark');
            $table->string('bg_image_url', 1024)->nullable();
            // Spec chỉ cho 40 | 60 | 80 — validate ở controller.
            $table->unsignedTinyInteger('overlay_level')->default(60);
            $table->string('sponsor_logo_url', 1024)->nullable();
            $table->char('public_token', 32)->unique();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livescore_sessions');
    }
};