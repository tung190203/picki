<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_merged')->default(false)->after('is_anchor');
            $table->unsignedBigInteger('merged_into_user_id')->nullable()->after('is_merged');

            $table->index('is_merged', 'idx_users_is_merged');
            $table->index('merged_into_user_id', 'idx_users_merged_into');

            $table->foreign('merged_into_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['merged_into_user_id']);
            $table->dropIndex('idx_users_merged_into');
            $table->dropIndex('idx_users_is_merged');
            $table->dropColumn(['is_merged', 'merged_into_user_id']);
        });
    }
};
