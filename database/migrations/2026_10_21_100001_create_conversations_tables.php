<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // dm, tournament, mini_tournament, club
            $table->unsignedBigInteger('target_id')->nullable(); // tournament_id, mini_tournament_id, club_id
            $table->string('title')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'target_id']);
            $table->index(['is_archived', 'last_message_at']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20)->default('member'); // admin, member
            $table->boolean('is_stranger')->default(false); // Đối với DM: user này xem đối phương là người lạ
            $table->boolean('has_replied')->default(false); // User này đã từng trả lời trong cuộc hội thoại chưa
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->boolean('is_muted')->default(false);
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'is_stranger']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 30)->default('text'); // text, image, voice, file, card_mini_tournament, card_tournament, card_player, card_club
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
