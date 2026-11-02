<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gộp estimated_level_min/max thành 1 cột `estimated_level` (số cố định).
 *
 * Trình độ ước tính là 1 số, không phải khoảng. Nếu dữ liệu cũ chỉ có 1 trong 2
 * cột (hoặc cả 2 khác nhau) thì lấy min (ưu tiên giá trị đã nhập).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mini_participants', 'estimated_level_min')) {
            return;
        }

        Schema::table('mini_participants', function (Blueprint $table) {
            $table->decimal('estimated_level', 3, 1)
                ->nullable()
                ->after('guarantor_user_id')
                ->comment('Trình độ ước tính của guest (1.0 - 8.0)');
        });

        DB::table('mini_participants')
            ->whereNotNull('estimated_level_min')
            ->update(['estimated_level' => DB::raw('estimated_level_min')]);

        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn(['estimated_level_min', 'estimated_level_max']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('mini_participants', 'estimated_level')) {
            return;
        }

        Schema::table('mini_participants', function (Blueprint $table) {
            $table->decimal('estimated_level_min', 3, 1)->nullable()->after('guarantor_user_id');
            $table->decimal('estimated_level_max', 3, 1)->nullable()->after('estimated_level_min');
        });

        DB::table('mini_participants')
            ->whereNotNull('estimated_level')
            ->update([
                'estimated_level_min' => DB::raw('estimated_level'),
                'estimated_level_max' => DB::raw('estimated_level'),
            ]);

        Schema::table('mini_participants', function (Blueprint $table) {
            $table->dropColumn('estimated_level');
        });
    }
};