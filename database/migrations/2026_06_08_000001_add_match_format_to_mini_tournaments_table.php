<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->string('match_format', 30)
                ->default('standard')
                ->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn('match_format');
        });
    }
};
