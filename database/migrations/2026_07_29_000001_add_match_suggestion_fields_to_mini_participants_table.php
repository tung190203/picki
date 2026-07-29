<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->enum('match_tier', ['A', 'B'])->nullable()->after('player_group');
            $table->boolean('skip_next_round')->default(false)->after('match_tier');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn(['match_tier', 'skip_next_round']);
        });
    }
};
