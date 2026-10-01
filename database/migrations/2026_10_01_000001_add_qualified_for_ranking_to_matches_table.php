<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('qualified_for_ranking')->nullable()->after('is_third_place');
            $table->index('qualified_for_ranking', 'matches_qualified_for_ranking_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_qualified_for_ranking_idx');
            $table->dropColumn('qualified_for_ranking');
        });
    }
};