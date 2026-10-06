<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->enum('recruitment_status', ['open', 'closed'])
                ->default('closed')
                ->after('is_banned')
                ->comment('Trạng thái tuyển thành viên. Chỉ super_admin được đổi.');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('recruitment_status');
        });
    }
};
