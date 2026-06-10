<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->string('player_group', 20)->nullable()->after('is_pending_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('player_group');
        });
    }
};
