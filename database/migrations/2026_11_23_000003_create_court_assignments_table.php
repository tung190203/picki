<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->enum('unit_type', ['group', 'slot']);
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->string('slot_round', 10)->nullable();
            $table->unsignedInteger('slot_no')->nullable();
            $table->foreignId('court_id')->constrained('tournament_courts')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tournament_id', 'unit_type', 'group_id'], 'ca_tour_group_idx');
            $table->index(['tournament_id', 'unit_type', 'slot_round', 'slot_no'], 'ca_tour_slot_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_assignments');
    }
};
