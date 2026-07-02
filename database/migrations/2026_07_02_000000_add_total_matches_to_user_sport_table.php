<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sport', function (Blueprint $table) {
            $table->unsignedInteger('total_matches')->default(0)->after('tier');
            $table->index(['sport_id', 'total_matches'], 'idx_sport_total_matches');
        });
    }

    public function down(): void
    {
        Schema::table('user_sport', function (Blueprint $table) {
            $table->dropIndex('idx_sport_total_matches');
            $table->dropColumn('total_matches');
        });
    }
};
