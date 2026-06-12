<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->unsignedBigInteger('bye_participant_id')->nullable()->after('participant_win_id');
            $table->foreign('bye_participant_id')->references('id')->on('mini_participants')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('mini_matches', function (Blueprint $table) {
            $table->dropForeign(['bye_participant_id']);
            $table->dropColumn('bye_participant_id');
        });
    }
};
