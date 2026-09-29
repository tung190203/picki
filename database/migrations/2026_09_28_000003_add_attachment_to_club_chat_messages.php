<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_chat_messages', function (Blueprint $table) {
            $table->string('attachment_type', 30)->nullable()->after('type'); // image | video | file | tournament
            $table->json('attachment_meta')->nullable()->after('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::table('club_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_type', 'attachment_meta']);
        });
    }
};
