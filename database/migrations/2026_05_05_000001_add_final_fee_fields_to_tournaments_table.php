<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'final_fee_per_person')) {
                $table->unsignedInteger('final_fee_per_person')->nullable()->after('fee_amount')
                    ->comment('Phí cố định mỗi người sau khi lock (dùng cho auto_split_fee)');
            }
            if (!Schema::hasColumn('tournaments', 'auto_payment_created')) {
                $table->boolean('auto_payment_created')->default(false)->after('auto_split_fee')
                    ->comment('Flag báo đã tạo auto-split payments, ngăn thanh toán sớm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                Schema::hasColumn('tournaments', 'final_fee_per_person') ? 'final_fee_per_person' : null,
                Schema::hasColumn('tournaments', 'auto_payment_created') ? 'auto_payment_created' : null,
            ]);
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
