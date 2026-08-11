<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedBigInteger('leg_referee_id')->nullable()->after('referee_id');
            $table->foreign('leg_referee_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['leg_referee_id']);
            $table->dropColumn('leg_referee_id');
        });
    }
};
