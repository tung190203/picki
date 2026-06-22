<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'ranking_matches'],
            [
                'value' => '10',
                'type' => 'number',
                'group' => 'leaderboard',
                'description' => 'So tran toi thieu de duoc xuat hien tren bang xep hang',
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'ranking_matches')->delete();
    }
};
