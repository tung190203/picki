<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_matches', function (Blueprint $table) {
            $table->boolean('is_referee_scoring')->default(false)->after('competition_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('quick_matches', function (Blueprint $table) {
            $table->dropColumn('is_referee_scoring');
        });
    }
};
