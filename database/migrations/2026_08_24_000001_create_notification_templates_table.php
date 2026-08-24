<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->string('name');
            $table->string('title', 50);
            $table->string('content', 150);
            $table->string('action_type')->nullable();
            $table->unsignedBigInteger('action_id')->nullable();
            $table->string('recipient_type');
            $table->json('recipient_config');
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
