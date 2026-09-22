<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Seed default rows with NULL value — admin fills these in via the admin UI.
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'goong.api_key'],
            [
                'value'       => null,
                'type'        => 'string',
                'group'       => 'map_provider',
                'description' => 'Goong REST API key (server-side only). Get it from goong.io dashboard.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'goong.map_key'],
            [
                'value'       => null,
                'type'        => 'string',
                'group'       => 'map_provider',
                'description' => 'Goong Map tiles key (public, sent to browser). Get it from goong.io dashboard.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'goong.api_key')
            ->delete();
        DB::table('system_settings')
            ->where('key', 'goong.map_key')
            ->delete();
    }
};
