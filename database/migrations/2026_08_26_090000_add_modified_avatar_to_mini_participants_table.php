<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->string('modified_avatar')->nullable()->after('guest_avatar');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('modified_avatar');
        });
    }
};
