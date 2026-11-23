<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('referee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tournament_id', 'sort_order']);
            $table->index(['referee_user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_courts');
    }
};
