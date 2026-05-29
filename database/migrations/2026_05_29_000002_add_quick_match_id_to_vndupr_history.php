<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('vndupr_history', 'quick_match_id')) {
            Schema::table('vndupr_history', function (Blueprint $table) {
                $table->unsignedBigInteger('quick_match_id')->nullable()->after('mini_match_id');
                $table->foreign('quick_match_id')->references('id')->on('quick_matches')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vndupr_history', 'quick_match_id')) {
            Schema::table('vndupr_history', function (Blueprint $table) {
                $table->dropForeign(['quick_match_id']);
                $table->dropColumn('quick_match_id');
            });
        }
    }
};
