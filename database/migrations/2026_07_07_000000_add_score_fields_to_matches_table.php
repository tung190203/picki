<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('match_version')->default(1)->after('winner_id');
            $table->enum('live_status', ['waiting', 'playing', 'timeout', 'between_sets', 'finished', 'cancelled'])->default('waiting')->after('match_version');
            $table->timestamp('started_at')->nullable()->after('live_status');
            $table->tinyInteger('current_set')->unsigned()->default(1)->after('started_at');
            $table->unsignedBigInteger('serving_team_id')->nullable()->after('current_set');
            $table->tinyInteger('team1_timeout_used')->unsigned()->default(0)->after('serving_team_id');
            $table->tinyInteger('team2_timeout_used')->unsigned()->default(0)->after('team1_timeout_used');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn([
                'match_version',
                'live_status',
                'started_at',
                'current_set',
                'serving_team_id',
                'team1_timeout_used',
                'team2_timeout_used',
            ]);
        });
    }
};
