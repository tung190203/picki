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
            if (!Schema::hasColumn('mini_participants', 'invited_by')) {
                $table->unsignedBigInteger('invited_by')->nullable()->after('is_invited');
                $table->foreign('invited_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participants', 'invited_by')) {
                $table->dropForeign(['invited_by']);
                $table->dropColumn('invited_by');
            }
        });
    }
};
