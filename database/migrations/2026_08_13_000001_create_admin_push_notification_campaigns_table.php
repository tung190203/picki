<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: skip nếu table đã tồn tại (DB đã được tạo thủ công / chạy trước đó)
        if (Schema::hasTable('admin_push_notification_campaigns')) {
            return;
        }

        Schema::create('admin_push_notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 50);
            $table->string('content', 150);
            $table->string('image_url')->nullable();

            $table->string('action_type', 20);
            $table->unsignedBigInteger('action_id')->nullable();

            $table->string('recipient_type', 20);
            $table->json('recipient_config')->nullable();

            $table->unsignedInteger('estimated_recipient_count')->default(0);
            $table->unsignedInteger('actual_recipient_count')->nullable();
            $table->unsignedInteger('success_count')->nullable();
            $table->unsignedInteger('failure_count')->nullable();

            $table->string('send_type', 20);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->string('status', 20)->default('DRAFT');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_by');
            $table->index('recipient_type');
            $table->index('created_at');
            $table->index(['status', 'scheduled_at'], 'admin_push_notif_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_notification_campaigns');
    }
};
