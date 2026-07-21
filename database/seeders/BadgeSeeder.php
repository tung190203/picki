<?php

namespace Database\Seeders;

use App\Enums\BadgeType;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Syncs badges from legacy is_verified/is_anchor fields to new user_badges table.
     */
    public function run(): void
    {
        $badgeService = app(BadgeService::class);
        $syncedCount = $badgeService->syncAllFromLegacyFields();

        $this->command->info("Synced badges for {$syncedCount} users from legacy fields.");
    }
}
