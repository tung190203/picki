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
            if (!Schema::hasColumn('mini_participants', 'is_absent')) {
                $table->boolean('is_absent')
                    ->default(false)
                    ->after('guarantor_user_id')
                    ->comment('true: vang mat, false: co mat');
                $table->index(['mini_tournament_id', 'is_absent']);
            }
            if (!Schema::hasColumn('mini_participants', 'checked_in_at')) {
                $table->timestamp('checked_in_at')
                    ->nullable()
                    ->after('is_absent')
                    ->comment('Thoi gian check in');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participants', 'checked_in_at')) {
                $table->dropColumn('checked_in_at');
            }
            if (Schema::hasColumn('mini_participants', 'is_absent')) {
                $table->dropIndex(['mini_tournament_id', 'is_absent']);
                $table->dropColumn('is_absent');
            }
        });
    }
};
