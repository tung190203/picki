<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->tinyInteger('team_score')->unsigned()->default(0)->after('set_number');
            $table->tinyInteger('opponent_score')->unsigned()->default(0)->after('team_score');
            $table->tinyInteger('serving_position')->unsigned()->default(0)->comment('0 or 1 - player position within team')->after('opponent_score');
        });
    }

    public function down(): void
    {
        Schema::table('match_results', function (Blueprint $table) {
            $table->dropColumn([
                'team_score',
                'opponent_score',
                'serving_position',
            ]);
        });
    }
};
