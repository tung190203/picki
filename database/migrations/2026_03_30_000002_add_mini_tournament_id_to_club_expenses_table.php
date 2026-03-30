<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('mini_tournament_id')
                ->nullable()
                ->after('club_activity_id');

            $table->foreign('mini_tournament_id')
                ->references('id')
                ->on('mini_tournaments')
                ->onDelete('set null');

            $table->index('mini_tournament_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_expenses', function (Blueprint $table) {
            $table->dropForeign(['mini_tournament_id']);
            $table->dropIndex(['mini_tournament_id']);
            $table->dropColumn('mini_tournament_id');
        });
    }
};
