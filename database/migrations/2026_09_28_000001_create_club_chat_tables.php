<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('club_id')->unique();
            $table->string('name')->default('Nhóm chung CLB');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamps();

            $table->foreign('club_id')->references('id')->on('clubs')->onDelete('cascade');
        });

        Schema::create('club_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->string('type', 20)->default('text');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('club_chat_conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_chat_messages');
        Schema::dropIfExists('club_chat_conversations');
    }
};
