<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (!Schema::hasColumn('mini_participants', 'is_invited')) {
                $table->boolean('is_invited')
                    ->default(false)
                    ->after('is_confirmed')
                    ->comment('true: duoc moi, false: tu dang ky');
                $table->index(['mini_tournament_id', 'is_invited']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participants', 'is_invited')) {
                $table->dropIndex(['mini_tournament_id', 'is_invited']);
                $table->dropColumn('is_invited');
            }
        });
    }
};
