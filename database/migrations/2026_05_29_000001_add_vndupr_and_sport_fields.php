<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_histories', function (Blueprint $table) {
            $table->decimal('vndupr_score_change', 8, 2)->nullable()->after('played_at');
        });

        Schema::table('quick_matches', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_matches', 'sport_id')) {
                $table->unsignedBigInteger('sport_id')->default(1)->after('is_referee_scoring');
                $table->foreign('sport_id')->references('id')->on('sports')->onDelete('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quick_matches', function (Blueprint $table) {
            if (Schema::hasColumn('quick_matches', 'sport_id')) {
                $table->dropForeign(['sport_id']);
                $table->dropColumn('sport_id');
            }
        });

        Schema::table('match_histories', function (Blueprint $table) {
            $table->dropColumn('vndupr_score_change');
        });
    }
};
