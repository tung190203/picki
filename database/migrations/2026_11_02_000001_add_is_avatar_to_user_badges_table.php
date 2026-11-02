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
        if (!Schema::hasColumn('user_badges', 'is_avatar')) {
            Schema::table('user_badges', function (Blueprint $table) {
                $table->boolean('is_avatar')->default(false)->after('is_featured');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('user_badges', 'is_avatar')) {
            Schema::table('user_badges', function (Blueprint $table) {
                $table->dropColumn('is_avatar');
            });
        }
    }
};

