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
        Schema::table('participants', function (Blueprint $table) {
            $table->decimal('rating_before', 4, 2)->nullable()->after('is_absent');
            $table->decimal('rating_after', 4, 2)->nullable()->after('rating_before');
            $table->integer('rank_before')->nullable()->after('rating_after');
            $table->integer('rank_after')->nullable()->after('rank_before');
            $table->integer('rank_change')->nullable()->after('rank_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn([
                'rating_before',
                'rating_after',
                'rank_before',
                'rank_after',
                'rank_change',
            ]);
        });
    }
};
