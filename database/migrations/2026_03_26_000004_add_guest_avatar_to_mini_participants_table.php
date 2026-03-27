<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->string('guest_avatar')->nullable()->after('guest_phone');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('guest_avatar');
        });
    }
};
