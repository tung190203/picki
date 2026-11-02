<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng `match_results` phải bỏ cột `participant_id`.
 *
 * Migration gốc (2025_10_15_071952) cố drop FK rồi drop cột, nhưng trên
 * MariaDB: FK bị đã để lại orphan index `match_results_participant_id_foreign`,
 * nên `DROP COLUMN participant_id` bị chặn (errno 1072). Lỗi bị try/catch nuốt
 * im lặng => migration vẫn đánh dấu "Ran" nhưng cột còn nguyên, và mọi INSERT
 * vào `match_results` fail vì cột NOT NULL không có default.
 *
 * Migration này dọn index mồ côi rồi drop cột — idempotent, chạy an toàn trên
 * DB đã có cột (production) và DB mới (fresh migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('match_results', 'participant_id')) {
            return;
        }

        // Bỏ FK nếu còn (tên FK có thể khác tuỳ lúc tạo bảng).
        foreach (DB::select(
            "SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'match_results'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        ) as $fk) {
            $name = $fk->CONSTRAINT_NAME;
            try {
                DB::statement("ALTER TABLE `match_results` DROP FOREIGN KEY `{$name}`");
            } catch (\Throwable $e) {
                // FK không tồn tại -> bỏ qua, index mồ côi vẫn được xử lý bên dưới.
            }
        }

        // Bỏ index mồ côi (FK đã drop nhưng index còn) -> mới drop được cột.
        foreach (DB::select("SHOW INDEX FROM `match_results`") as $index) {
            $indexName = $index->Key_name ?? null;
            if (!$indexName || $indexName === 'PRIMARY') {
                continue;
            }
            $columns = DB::select(
                "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'match_results'
                   AND INDEX_NAME = ?",
                [$indexName]
            );
            $usesParticipant = false;
            foreach ($columns as $col) {
                if (($col->COLUMN_NAME ?? null) === 'participant_id') {
                    $usesParticipant = true;
                    break;
                }
            }
            if ($usesParticipant) {
                try {
                    DB::statement("ALTER TABLE `match_results` DROP INDEX `{$indexName}`");
                } catch (\Throwable $e) {
                    // Index đã bị drop -> bỏ qua.
                }
            }
        }

        try {
            DB::statement('ALTER TABLE `match_results` DROP COLUMN `participant_id`');
        } catch (\Throwable $e) {
            // Đã drop rồi -> bỏ qua.
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('match_results', 'participant_id')) {
            return;
        }

        Schema::table('match_results', function (Blueprint $table) {
            $table->unsignedBigInteger('participant_id')->nullable()->after('match_id');
        });
    }
};
