<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->boolean('use_club_fund')->default(false)->after('qr_code_url');
            $table->unsignedBigInteger('club_fund_collection_id')->nullable()->after('use_club_fund');
            $table->foreign('club_fund_collection_id')
                  ->references('id')
                  ->on('club_fund_collections')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mini_tournaments', function (Blueprint $table) {
            $table->dropForeign(['club_fund_collection_id']);
            $table->dropColumn(['use_club_fund', 'club_fund_collection_id']);
        });
    }
};
