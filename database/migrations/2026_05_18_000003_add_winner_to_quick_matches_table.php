<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_matches', function (Blueprint $table) {
            $table->string('winner', 10)->nullable()->after('score');
            $table->timestamp('confirmed_at')->nullable()->after('winner');
        });
    }

    public function down(): void
    {
        Schema::table('quick_matches', function (Blueprint $table) {
            $table->dropColumn(['winner', 'confirmed_at']);
        });
    }
};
