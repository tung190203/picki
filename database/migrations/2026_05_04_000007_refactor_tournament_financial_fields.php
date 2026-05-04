<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Thêm has_fee (thay thế logic fee enum free/pair)
            if (!Schema::hasColumn('tournaments', 'has_fee')) {
                $table->boolean('has_fee')->default(false)->after('auto_split_fee')
                    ->comment('True: có thu phí, False: miễn phí');
            }

            // Thêm fee_amount (thay thế standard_fee_amount)
            if (!Schema::hasColumn('tournaments', 'fee_amount')) {
                $table->unsignedInteger('fee_amount')->nullable()->after('has_fee')
                    ->comment('Số tiền phí tham gia (tổng hoặc cố định/người tùy auto_split_fee)');
            }

            // Thêm allow_cancellation
            if (!Schema::hasColumn('tournaments', 'allow_cancellation')) {
                $table->boolean('allow_cancellation')->default(true)->after('fee_amount')
                    ->comment('Cho phép hủy kèo trước khi bắt đầu');
            }

            // Thêm cancellation_duration (số phút trước start_time được phép hủy)
            if (!Schema::hasColumn('tournaments', 'cancellation_duration')) {
                $table->unsignedInteger('cancellation_duration')->nullable()->after('allow_cancellation')
                    ->comment('Số phút trước start_time cho phép hủy (VD: 60 = 1 tiếng trước khi bắt đầu)');
            }

            // Đảm bảo club_fund_collection_id có trong bảng (fix mass assignment)
            if (!Schema::hasColumn('tournaments', 'club_fund_collection_id')) {
                $table->unsignedBigInteger('club_fund_collection_id')->nullable()->after('tournament_fund_collection_id');
            }
        });

        // Migrate dữ liệu cũ: fee = 'pair' → has_fee = true, fee_amount = standard_fee_amount
        // fee = 'free' → has_fee = false, fee_amount = null
        if (Schema::hasColumn('tournaments', 'fee') && Schema::hasColumn('tournaments', 'has_fee')) {
            DB::statement("
                UPDATE tournaments
                SET has_fee = CASE WHEN fee = 'pair' THEN 1 ELSE 0 END,
                    fee_amount = CASE WHEN fee = 'pair' THEN COALESCE(standard_fee_amount, 0) ELSE NULL END
                WHERE fee IS NOT NULL
            ");
        }

        // Xóa cột cũ nếu tồn tại
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'fee')) {
                $table->dropColumn('fee');
            }
            if (Schema::hasColumn('tournaments', 'standard_fee_amount')) {
                $table->dropColumn('standard_fee_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Khôi phục cột cũ
            if (!Schema::hasColumn('tournaments', 'fee')) {
                $table->string('fee', 20)->nullable()->after('player_per_team')
                    ->comment('free/pair');
            }
            if (!Schema::hasColumn('tournaments', 'standard_fee_amount')) {
                $table->unsignedInteger('standard_fee_amount')->nullable()->after('fee');
            }
        });

        // Khôi phục dữ liệu
        if (Schema::hasColumn('tournaments', 'fee') && Schema::hasColumn('tournaments', 'has_fee')) {
            DB::statement("
                UPDATE tournaments
                SET fee = CASE WHEN has_fee = 1 THEN 'pair' ELSE 'free' END,
                    standard_fee_amount = CASE WHEN has_fee = 1 THEN fee_amount ELSE 0 END
                WHERE has_fee IS NOT NULL
            ");
        }

        // Xóa cột mới
        Schema::table('tournaments', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                Schema::hasColumn('tournaments', 'has_fee') ? 'has_fee' : null,
                Schema::hasColumn('tournaments', 'fee_amount') ? 'fee_amount' : null,
                Schema::hasColumn('tournaments', 'allow_cancellation') ? 'allow_cancellation' : null,
                Schema::hasColumn('tournaments', 'cancellation_duration') ? 'cancellation_duration' : null,
            ]);
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
