<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'latest_used_qr')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('latest_used_qr')->nullable()->after('avatar_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'latest_used_qr')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('latest_used_qr');
            });
        }
    }
};
