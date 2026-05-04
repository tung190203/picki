<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('has_financial_management')->default(false)->after('creator_join');
            $table->boolean('auto_split_fee')->default(false)->after('has_financial_management');
            $table->string('fee_description', 500)->nullable()->after('auto_split_fee');
            $table->string('qr_code_url')->nullable()->after('fee_description');
            $table->boolean('use_club_fund')->default(false)->after('qr_code_url');
            $table->boolean('included_in_club_fund')->default(false)->after('use_club_fund');
            $table->unsignedBigInteger('tournament_fund_collection_id')->nullable()->after('included_in_club_fund');
            $table->unsignedBigInteger('club_fund_collection_id')->nullable()->after('tournament_fund_collection_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign(['tournament_fund_collection_id']);
            $table->dropForeign(['club_fund_collection_id']);
            $table->dropColumn([
                'has_financial_management',
                'auto_split_fee',
                'fee_description',
                'qr_code_url',
                'use_club_fund',
                'included_in_club_fund',
                'tournament_fund_collection_id',
                'club_fund_collection_id',
            ]);
        });
    }
};
