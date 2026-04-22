<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 50)->default('string');
            $table->string('group', 100)->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'k_factor', 'value' => '32', 'type' => 'number', 'group' => 'rating', 'description' => 'K-factor base value'],
            ['key' => 'service_fee_percent', 'value' => '5.5', 'type' => 'number', 'group' => 'payment', 'description' => 'Service fee percentage'],
            ['key' => 'auto_confirm_hours', 'value' => '24', 'type' => 'number', 'group' => 'match', 'description' => 'Auto confirm match after X hours'],
            ['key' => 'features', 'value' => json_encode([
                'ai_assistant' => true,
                'online_payment' => true,
                'maintenance_mode' => false,
            ]), 'type' => 'json', 'group' => 'features', 'description' => 'Feature toggles'],
        ];

        DB::table('system_settings')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
