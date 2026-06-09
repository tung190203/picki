<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->string('session_status', 30)->nullable()->after('match_format');
            $table->dateTime('session_started_at')->nullable()->after('session_status');
            $table->unsignedTinyInteger('scheduled_court_count')->default(2)->after('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn(['session_status', 'session_started_at', 'scheduled_court_count']);
        });
    }
};
