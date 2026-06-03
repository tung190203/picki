<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->string('zalo_link', 500)->nullable()->after('qr_code_url');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('zalo_link', 500)->nullable()->after('qr_code_url');
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropColumn('zalo_link');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('zalo_link');
        });
    }
};
