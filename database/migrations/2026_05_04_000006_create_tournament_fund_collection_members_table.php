<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_fund_collection_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_fund_collection_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('tournament_fund_collection_id', 'tfcm_tfc_id_foreign')
                  ->references('id')->on('tournament_fund_collections')->cascadeOnDelete();
            $table->foreign('user_id', 'tfcm_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tournament_fund_collection_id', 'user_id'], 'tfcm_tfc_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_fund_collection_members');
    }
};
