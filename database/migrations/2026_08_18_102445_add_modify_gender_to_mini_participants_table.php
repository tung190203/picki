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
            $table->unsignedTinyInteger('modify_gender')->nullable()->after('modified_score')->comment('1: Nam, 2: Nu - Gioi tinh duoc chinh sua boi organizer/admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('modify_gender');
        });
    }
};
