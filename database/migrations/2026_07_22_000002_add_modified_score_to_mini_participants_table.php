<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->decimal('modified_score', 4, 2)->nullable()->after('rank_change');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('modified_score');
        });
    }
};
