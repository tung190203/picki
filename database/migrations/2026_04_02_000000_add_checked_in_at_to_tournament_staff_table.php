<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('role');
            $table->boolean('is_absent')->default(false)->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'is_absent']);
        });
    }
};
