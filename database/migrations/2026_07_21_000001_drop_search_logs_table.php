<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('search_logs');
    }

    public function down(): void
    {
        Schema::create('search_logs', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tab', 20)->comment('match, tournament, club, user, court');
            $table->string('keyword', 255)->nullable();
            $table->string('filters_json', 500)->nullable();
            $table->string('sub_tab', 20)->nullable();
            $table->string('result_count', 20)->nullable()->comment('cached approximate count');
            $table->timestamp('searched_at')->useCurrent();
            $table->index(['tab', 'searched_at']);
            $table->index('user_id');
        });
    }
};
