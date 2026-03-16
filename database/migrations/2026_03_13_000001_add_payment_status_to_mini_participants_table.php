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
            if (!Schema::hasColumn('mini_participants', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'confirmed', 'cancelled'])
                    ->default('pending')
                    ->after('is_confirmed');
                $table->index(['mini_tournament_id', 'payment_status']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mini_participants', function (Blueprint $table) {
            if (Schema::hasColumn('mini_participants', 'payment_status')) {
                $table->dropIndex(['mini_tournament_id', 'payment_status']);
                $table->dropColumn('payment_status');
            }
        });
    }
};
