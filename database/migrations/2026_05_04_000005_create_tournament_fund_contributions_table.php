<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_fund_contributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_fund_collection_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 15, 2);
            $table->string('receipt_url')->nullable();
            $table->string('note')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tournament_fund_collection_id', 'tfc_tournament_fund_collection_id_foreign')
                  ->references('id')->on('tournament_fund_collections')->cascadeOnDelete();
            $table->foreign('user_id', 'tfc_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by', 'tfc_created_by_foreign')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_fund_contributions');
    }
};
