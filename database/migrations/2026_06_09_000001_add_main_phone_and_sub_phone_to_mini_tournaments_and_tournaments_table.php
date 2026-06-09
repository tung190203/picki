<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->string('main_phone', 20)->nullable()->after('zalo_link');
            $table->string('sub_phone', 20)->nullable()->after('main_phone');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('main_phone', 20)->nullable()->after('zalo_link');
            $table->string('sub_phone', 20)->nullable()->after('main_phone');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn('main_phone');
            $table->dropColumn('sub_phone');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('main_phone');
            $table->dropColumn('sub_phone');
        });
    }
};
