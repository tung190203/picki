<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('score_type', 20);
            $table->decimal('submitted_score', 4, 3);
            $table->string('image_path');
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('award_anchor_badge')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('score_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_verification_requests');
    }
};
