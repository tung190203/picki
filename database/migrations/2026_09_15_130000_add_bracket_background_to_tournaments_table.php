<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Thêm cột `bracket_background` vào bảng `tournaments` để lưu đường dẫn ảnh background
     * cho modal BracketMixedPreview (sơ đồ thi đấu).
     * Nếu null thì frontend sẽ dùng ảnh mặc định của dự án.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('bracket_background')->nullable()->after('poster')
                ->comment('Đường dẫn ảnh background cho modal sơ đồ thi đấu (BracketMixedPreview)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('bracket_background');
        });
    }
};
