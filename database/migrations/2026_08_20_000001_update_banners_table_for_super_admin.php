<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'internal_name')) {
                $table->string('internal_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('banners', 'link_type')) {
                $table->string('link_type')->default('none')->after('image_url');
            }
            if (!Schema::hasColumn('banners', 'link_value')) {
                $table->string('link_value', 500)->nullable()->after('link_type');
            }
            if (!Schema::hasColumn('banners', 'start_date')) {
                $table->date('start_date')->nullable()->after('link_value');
            }
            if (!Schema::hasColumn('banners', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('banners', 'audience_segment_ids')) {
                $table->json('audience_segment_ids')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('banners', 'display_order')) {
                $table->integer('display_order')->default(0)->after('audience_segment_ids');
            }
            if (!Schema::hasColumn('banners', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('display_order');
            }
            if (!Schema::hasColumn('banners', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach ([
                'internal_name', 'link_type', 'link_value', 'start_date',
                'end_date', 'audience_segment_ids', 'display_order', 'is_enabled', 'created_by'
            ] as $col) {
                if (Schema::hasColumn('banners', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
