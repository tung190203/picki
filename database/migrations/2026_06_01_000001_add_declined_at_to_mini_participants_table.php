<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->timestamp('declined_at')->nullable()->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('declined_at');
        });
    }
};
