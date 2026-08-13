<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_merges', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('survivor_user_id');
            $table->unsignedBigInteger('merged_user_id');
            $table->unsignedBigInteger('performed_by');

            $table->unsignedInteger('duplicate_count')->default(0);
            $table->boolean('duplicate_override')->default(false);

            $table->unsignedInteger('matches_before_survivor')->default(0);
            $table->unsignedInteger('matches_before_merged')->default(0);
            $table->unsignedInteger('duplicate_matches_removed')->default(0);
            $table->unsignedInteger('matches_after_merge')->default(0);

            $table->decimal('estimated_rating', 8, 3)->nullable();
            $table->decimal('final_rating', 8, 3)->nullable();

            $table->json('selected_info_source')->nullable();
            $table->string('confirmation_name')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');

            $table->json('metadata')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->foreign('survivor_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('merged_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('performed_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->index('survivor_user_id', 'idx_user_merges_survivor');
            $table->index('merged_user_id', 'idx_user_merges_merged');
            $table->index('performed_by', 'idx_user_merges_performed');
            $table->index('status', 'idx_user_merges_status');
            $table->index('created_at', 'idx_user_merges_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_merges');
    }
};
