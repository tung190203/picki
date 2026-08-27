<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm court_id cho tournament_staff để giới hạn phạm vi trọng tài theo sân.
 *
 * Spec: Trọng tài Giải đấu có thể bị giới hạn theo court (sân).
 * - null = trọng tài có quyền score trên tất cả sân
 * - giá trị cụ thể = chỉ được score trận ở sân đó
 *
 * NOTE: Bảng `competition_courts` hiện không tồn tại trong schema — sân hiện được
 * lưu dưới dạng string trong `matches.court`. Vì vậy migration này KHÔNG thêm
 * foreign key constraint, chỉ thêm column nullable integer để giữ tương thích.
 * Khi nào có bảng competition_courts chính thức, có thể FK sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('court_id')->nullable()->after('is_absent');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_staff', function (Blueprint $table) {
            $table->dropColumn('court_id');
        });
    }
};