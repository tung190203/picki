<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->boolean('included_in_club_fund')->default(false)->after('use_club_fund');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn('included_in_club_fund');
        });
    }
};
