<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('round_number')->nullable()->after('note');
            $table->boolean('is_bye')->default(false)->after('round_number');
            $table->unsignedInteger('team_1_score')->nullable()->after('is_bye');
            $table->unsignedInteger('team_2_score')->nullable()->after('team_1_score');
        });
    }

    public function down(): void
    {
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->dropColumn(['round_number', 'is_bye', 'team_1_score', 'team_2_score']);
        });
    }
};
