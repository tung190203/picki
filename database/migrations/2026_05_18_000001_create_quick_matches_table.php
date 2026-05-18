<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_matches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('note')->nullable();
            $table->json('team_a'); // array of user IDs, max 2
            $table->json('team_b'); // array of user IDs, max 2
            $table->enum('match_type', ['rank', 'casual'])->default('rank');
            $table->string('qr_code')->unique()->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed'])->default('pending');
            $table->json('score')->nullable(); // { team_a: [int], team_b: [int] }
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('competition_location_id')->nullable();
            $table->foreign('competition_location_id')->references('id')->on('competition_locations')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('qr_code');
            $table->index('status');
            $table->index('created_by');
            $table->index('competition_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_matches');
    }
};
