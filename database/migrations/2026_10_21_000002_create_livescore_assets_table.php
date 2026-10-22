<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kho file tải lên. Việc "đang dùng ảnh nào" nằm ở localStorage máy chiếu,
        // bảng này chỉ giữ tệp + metadata để tái sử dụng đường dẫn.
        Schema::create('livescore_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id')->index();
            $table->enum('kind', ['bg', 'sponsor_logo']);
            $table->string('url', 1024);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livescore_assets');
    }
};