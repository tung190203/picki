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
        Schema::table('mini_tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('mini_tournaments', 'role_type')) {
                $table->dropColumn('role_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('role_type')->default(2)->comment('1:Organizer, 2:Participant');
        });
    }
};
