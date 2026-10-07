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
        Schema::create('badge_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        
        // Insert default types
        DB::table('badge_types')->insert([
            ['code' => 'rank', 'name' => 'Rank (Hạng)'],
            ['code' => 'achievement', 'name' => 'Achievement (Thành tựu)'],
            ['code' => 'activity', 'name' => 'Activity (Hoạt động)'],
            ['code' => 'role', 'name' => 'Role (Vai trò)'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_types');
    }
};
